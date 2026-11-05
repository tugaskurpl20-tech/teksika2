<!-- Partial: resources/views/user/request/_estimasi.blade.php -->
<style>
    .map-picker { margin: 4px 0 16px; }
    .map-picker-label { display: block; font-weight: 700; font-size: .92rem; color: #1f2937; margin-bottom: 8px; }
    .map-picker-search { display: flex; flex-wrap: wrap; gap: 10px; align-items: stretch; margin-bottom: 12px; }
    .map-picker-input {
        flex: 1 1 220px; min-width: 0; height: 42px; padding: 0 14px;
        border: 1px solid #e2e5ea; background: #f4f4f5; border-radius: 10px;
        font-size: .86rem; font-family: inherit; color: #1f2937; transition: border-color .15s, box-shadow .15s, background .15s;
    }
    .map-picker-input:focus { outline: none; background: #fff; border-color: #1a56a8; box-shadow: 0 0 0 3px rgba(26, 86, 168, .12); }
    .map-picker-btn {
        height: 42px; padding: 0 18px; border: none; border-radius: 10px; background: #1a56a8; color: #fff;
        font-weight: 700; font-size: .82rem; font-family: inherit; cursor: pointer; white-space: nowrap;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: background .15s;
    }
    .map-picker-btn:hover { background: #154689; }
    .map-picker-btn:disabled { opacity: .75; cursor: wait; }
    #requestMap { width: 100%; height: 320px; border: 1px solid #e2e5ea; border-radius: 12px; overflow: hidden; z-index: 0; }
    .map-status { margin-top: 10px; font-size: .84rem; color: #475569; display: flex; gap: 6px; align-items: flex-start; }
    .map-status-ok { color: #166534; }
    .map-status-warn { color: #b45309; }
    .map-status-error { color: #b91c1c; font-weight: 600; }
    .map-status-loading { color: #1a56a8; }
    .map-coord { margin-top: 4px; font-size: .76rem; color: #94a3b8; }
    @media (max-width: 520px) { .map-picker-btn { flex: 1 1 calc(50% - 5px); } }
</style>

<div class="map-picker algorithm-estimasi-wrapper">
    @php
        $savedLat = old('latitude', (isset($user) && !empty($user->latitude)) ? $user->latitude : (auth()->user()->latitude ?? '-6.4239'));
        $savedLon = old('longitude', (isset($user) && !empty($user->longitude)) ? $user->longitude : (auth()->user()->longitude ?? '106.9401'));
        $hasCoords = old('latitude') || (isset($user) && !empty($user->latitude)) || !empty(auth()->user()->latitude);
    @endphp
    <!-- Hidden inputs for coordinates -->
    <input type="hidden" name="latitude" id="latInput" value="{{ $savedLat }}" data-picked="{{ $hasCoords ? 1 : 0 }}">
    <input type="hidden" name="longitude" id="lonInput" value="{{ $savedLon }}">

    <label class="map-picker-label" for="placeSearch">Titik lokasi pekerjaan</label>
    <div class="map-picker-search">
        <input type="text" id="placeSearch" class="map-picker-input" placeholder="Cari alamat atau nama tempat..." autocomplete="off" maxlength="255">
        <button type="button" id="btnCariTempat" class="map-picker-btn" onclick="searchPlaceNow()"><span>Cari</span></button>
        <button type="button" id="btnGetLocation" class="map-picker-btn" onclick="detectUserLocation()"><span>Gunakan lokasi saya</span></button>
    </div>

    <div id="locationNotice" style="display:none; font-size:0.8rem; color:#0284c7; background:#e0f2fe; padding:6px 12px; border-radius:8px; margin-bottom:10px;"></div>

    <!-- Peta Interaktif (Google Maps Utama / Leaflet OpenStreetMap Fallback) -->
    <div id="mapError" role="alert" style="display:none; color:#991b1b; background:#fef2f2; border:1px solid #fecaca; padding:10px 12px; border-radius:8px; margin-bottom:10px;"></div>
    <div id="requestMap"></div>

    <div id="mapStatus" class="map-status map-status-info">
        <span>{{ $hasCoords ? 'Lokasi dari koordinat tersimpan. Klik peta atau geser pin bila perlu mengubahnya.' : 'Lokasi belum diambil. Klik peta atau geser pin untuk memilih titik.' }}</span>
    </div>
    <div class="map-coord"><i class="fa-solid fa-map-pin" style="color:#ef4444;"></i> Koordinat: <span id="coordDisplay">{{ $savedLat }}, {{ $savedLon }}</span></div>

    <!-- Info Rute & Jarak -->
    <div class="route-status-pill" id="routeStatus">
        <i class="fa-solid fa-route"></i>
        <span>Pilih titik lokasi pada peta untuk melihat jarak rute dari kantor pusat.</span>
    </div>
</div>

<script>
const officePoint = [-6.4239, 106.9401];
let requestMap;
let homeMarker;
let officeMarker;
let routeLine;
let accuracyCircle = null;
let activeMapEngine = null; // 'google' | 'leaflet'
let lastRenderedRoute = null;
window.currentRouteDistanceKm = null;
window.currentRouteUnavailable = false;
let addressLookupDebounce = null;
let addressLookupSequence = 0;
let addressGeocodeDebounce = null;
let addressGeocodeSequence = 0;
let lastAddressGeocodeKey = '';
let addressDirty = false; // true jika pengguna mengetik/mengubah kolom alamat sendiri
window.locationPicked = (function () { const el = document.getElementById('latInput'); return !!(el && el.dataset.picked === '1'); })();
let searchButtonTimeout = null;
function byId(id) {
    return document.getElementById(id);
}

function clearAccuracyCircle() {
    if (activeMapEngine === 'google' && accuracyCircle && typeof accuracyCircle.setMap === 'function') {
        accuracyCircle.setMap(null);
    } else if (activeMapEngine === 'leaflet' && accuracyCircle && requestMap) {
        requestMap.removeLayer(accuracyCircle);
    }
    accuracyCircle = null;
}

function calculateStraightLineKm(lat1, lon1, lat2, lon2) {
    const toRad = deg => (deg * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return Math.round(6371 * c * 100) / 100;
}

/* =====================================================================
   ALAMAT TUNGGAL  <->  PETA
   - Kolom tampil: #alamatLengkap (teks bebas, satu-satunya kolom alamat)
   - Field tersembunyi (jalan, RT, RW, kelurahan, kecamatan, kabupaten,
     provinsi, kode pos) otomatis diisi agar controller tetap bekerja.
   ===================================================================== */

// Pecah teks alamat bebas menjadi jalan, RT, RW, kelurahan, kecamatan, kabupaten, provinsi, kode pos
function parseAddressText(text) {
    let t = (text || '').trim();
    let rt = '';
    let rw = '';
    let kodePos = '';

    const pos = t.match(/\b\d{5}\b/);
    if (pos) { kodePos = pos[0]; t = t.replace(pos[0], ' '); }

    const combo = t.match(/\bRT\s*\/\s*RW\.?\s*0*(\d{1,3})\s*[\/\-]\s*0*(\d{1,3})\b/i);
    if (combo) {
        rt = combo[1];
        rw = combo[2];
        t = t.replace(combo[0], ' ');
    } else {
        const m1 = t.match(/\bRT\.?\s*0*(\d{1,3})\b/i);
        if (m1) { rt = m1[1]; t = t.replace(m1[0], ' '); }
        const m2 = t.match(/\bRW\.?\s*0*(\d{1,3})\b/i);
        if (m2) { rw = m2[1]; t = t.replace(m2[0], ' '); }
    }

    const seg = t.split(',')
        .map(function (s) { return s.replace(/\s+/g, ' ').replace(/^[\s\-\/]+|[\s\-\/]+$/g, '').trim(); })
        .filter(Boolean);

    let jalan = '', kelurahan = '', kecamatan = '', kabupaten = '', provinsi = '';

    if (seg.length >= 5) {            // jalan, kelurahan, kecamatan, kabupaten, provinsi
        provinsi  = seg[seg.length - 1];
        kabupaten = seg[seg.length - 2];
        kecamatan = seg[seg.length - 3];
        kelurahan = seg[seg.length - 4];
        jalan     = seg.slice(0, -4).join(', ');
    } else if (seg.length >= 3) {     // jalan, kecamatan, kabupaten
        kabupaten = seg[seg.length - 1];
        kecamatan = seg[seg.length - 2];
        jalan     = seg.slice(0, -2).join(', ');
    } else if (seg.length === 2) {
        kabupaten = seg[1];
        jalan = seg[0];
    } else if (seg.length === 1) {
        kabupaten = seg[0];           // agar pencarian titik tetap terpicu
        jalan = seg[0];
    }
    kecamatan = kecamatan.replace(/^(kecamatan|kec\.?)\s+/i, '');

    return {
        jalan: jalan,
        kelurahan: kelurahan,
        kecamatan: kecamatan,
        kabupaten: kabupaten,
        provinsi: provinsi,
        kode_pos: kodePos,
        rt: rt,
        rw: rw,
        query: (kodePos ? seg.concat(kodePos) : seg).join(', ')
    };
}

function applyParsedParts(p) {
    const set = function (id, v) { const el = byId(id); if (el) el.value = v || ''; };
    set('alamatKabupaten', p.kabupaten);
    set('alamatKecamatan', p.kecamatan);
    set('alamatJalan', p.jalan);
    set('alamatRt', p.rt);
    set('alamatRw', p.rw);
    set('alamatKelurahan', p.kelurahan);
    set('alamatProvinsi', p.provinsi);
    set('alamatKodePos', p.kode_pos);
}

// Susun teks alamat tunggal (lengkap) dari bagian-bagian hasil reverse geocode
function composeAddressText(a) {
    const rtrw = [a.rt ? `RT ${a.rt}` : '', a.rw ? `RW ${a.rw}` : ''].filter(Boolean).join(' ');
    return [a.jalan, rtrw, a.kelurahan, a.kecamatan, a.kabupaten, a.provinsi, a.kode_pos]
        .map(function (s) { return (s || '').toString().trim(); })
        .filter(Boolean)
        .join(', ');
}

function clearAddressFields() {
    applyParsedParts({});
    const full = byId('alamatLengkap');
    if (full) full.value = '';
    const lokasiInput = byId('lokasiInput');
    if (lokasiInput) lokasiInput.value = '';
    addressDirty = false;
}

function setSearchButtonLoading(isLoading) {
    const btn = byId('btnCariTempat');
    if (!btn) return;
    clearTimeout(searchButtonTimeout);
    btn.disabled = !!isLoading;
    btn.innerHTML = isLoading
        ? '<i class="fa-solid fa-spinner fa-spin"></i> <span>Mencari...</span>'
        : '<span>Cari</span>';
    if (isLoading) {
        // pengaman: tombol tidak boleh macet jika server tidak merespons
        searchButtonTimeout = setTimeout(function () { setSearchButtonLoading(false); }, 15000);
    }
}

// Teks status di bawah peta. type: info | ok | warn | error | loading
function setMapStatus(type, html) {
    const el = byId('mapStatus');
    if (!el) return;
    const icons = { info: '', ok: 'fa-circle-check', warn: 'fa-triangle-exclamation', error: 'fa-circle-exclamation', loading: 'fa-spinner fa-spin' };
    const icon = icons[type] ? '<i class="fa-solid ' + icons[type] + '" style="margin-top:2px;"></i>' : '';
    el.className = 'map-status map-status-' + type;
    el.innerHTML = icon + '<span>' + html + '</span>';
}

function isLocationPicked() {
    return !!window.locationPicked;
}

function markLocationPicked() {
    window.locationPicked = true;
    setMapStatus('ok', 'Lokasi dipilih. Geser pin atau klik peta bila perlu menyesuaikan.');
}

// Tombol "Cari" / Enter di kolom pencarian peta: cari tempat, lalu alamat lengkap terisi otomatis
function searchPlaceNow() {
    const input = byId('placeSearch');
    const q = input ? input.value.trim() : '';
    if (!q) {
        setMapStatus('warn', 'Ketik alamat atau nama tempat terlebih dahulu, lalu tekan Cari.');
        if (input) input.focus();
        return;
    }
    const p = parseAddressText(q);
    p.query = q; // kirim teks asli agar nama tempat tetap bisa dicari
    runGeocode(p, false);
}

// Enter di kolom Alamat Lengkap: pindahkan pin ke alamat yang diketik (teks dipertahankan lalu dilengkapi)
function searchFromAddressField() {
    const full = byId('alamatLengkap');
    const text = full ? full.value.trim() : '';
    if (!text) return;
    addressDirty = true;
    syncLocationAddress({ lookupMap: false });
    runGeocode(parseAddressText(text), true);
}

function restoreAddressPlaceholder() {
    const full = byId('alamatLengkap');
    if (full && full.dataset.defaultPlaceholder) full.placeholder = full.dataset.defaultPlaceholder;
}

// Sinkronkan teks Alamat Lengkap ke field tersembunyi (dipanggil saat Enter / submit)
function syncLocationAddress(options = {}) {
    const full = byId('alamatLengkap');
    const text = full ? full.value.trim() : '';
    const parsed = parseAddressText(text);

    const jalanEl = byId('alamatJalan');
    if (addressDirty || !(jalanEl && jalanEl.value.trim())) {
        applyParsedParts(parsed);
    }

    const lokasiInput = byId('lokasiInput');
    if (lokasiInput) lokasiInput.value = text;
}

// Cari koordinat dari teks (Google Geocoder di browser, lalu backend sebagai cadangan)
// keepTyped = true  -> teks di kolom Alamat Lengkap dipertahankan lalu dilengkapi
// keepTyped = false -> kolom Alamat Lengkap diganti alamat hasil titik peta
function runGeocode(p, keepTyped) {
    if (!p || !p.query) return;
    clearTimeout(addressGeocodeDebounce);
    const sequence = ++addressGeocodeSequence;
    setSearchButtonLoading(true);
    setMapStatus('loading', 'Mencari titik lokasi di peta...');

    const found = function (lat, lng) {
        if (sequence !== addressGeocodeSequence) return;
        if (keepTyped) {
            setHomeLocation({ lat: lat, lng: lng }, { clearAddress: false, lookupAddress: false, zoom: 16 });
            lookupAddressFromCoordinates(lat, lng, { keepTyped: true });
        } else {
            setHomeLocation({ lat: lat, lng: lng }, { zoom: 16 });
        }
        setSearchButtonLoading(false);
    };

    const fail = function () {
        if (sequence !== addressGeocodeSequence) return;
        setSearchButtonLoading(false);
        setMapStatus('warn', 'Lokasi tidak ditemukan. Tambahkan nama jalan atau kota, atau klik/geser pin pada peta.');
    };

    const viaServer = function () {
        fetchServerGeocode(p, sequence, found, fail);
    };

    if (activeMapEngine === 'google' && window.google && window.google.maps && window.google.maps.Geocoder) {
        try {
            new google.maps.Geocoder().geocode({
                address: p.query + ', Indonesia',
                componentRestrictions: { country: 'ID' }
            }, function (results, gStatus) {
                if (sequence !== addressGeocodeSequence) return;
                if (gStatus === 'OK' && results && results[0] && results[0].geometry && results[0].geometry.location) {
                    const loc = results[0].geometry.location;
                    found(loc.lat(), loc.lng());
                    return;
                }
                viaServer();
            });
            return;
        } catch (e) {
            // lanjut ke backend
        }
    }
    viaServer();
}

// Backend (cascading Google -> Photon -> OpenStreetMap)
function fetchServerGeocode(p, sequence, found, fail) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        : (document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '');

    fetch("{{ route('user.request.lokasi-alamat') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ jalan: p.jalan, kecamatan: p.kecamatan, kabupaten: p.kabupaten })
    })
    .then(function (response) {
        if (!response.ok) throw new Error('Titik alamat belum ditemukan.');
        return response.json();
    })
    .then(function (data) {
        if (sequence !== addressGeocodeSequence) return;
        if (!data.success) throw new Error('Titik alamat belum ditemukan.');
        found(Number(data.location.latitude), Number(data.location.longitude));
    })
    .catch(fail);
}

// options.keepTyped = true -> dipakai setelah pencarian alamat: teks yang sudah diketik
// dipertahankan, lalu dilengkapi bagian wilayah yang belum ada (kelurahan, kota, provinsi, kode pos).
function lookupAddressFromCoordinates(latitude, longitude, options = {}) {
    const keepTyped = !!options.keepTyped;
    clearTimeout(addressLookupDebounce);
    const sequence = ++addressLookupSequence;
    const status = byId('addressStatus');
    const full = byId('alamatLengkap');
    if (status && !keepTyped) status.innerText = 'Mencari alamat dari titik peta...';
    if (full && !keepTyped) full.placeholder = 'Mencari alamat dari titik peta...';

    addressLookupDebounce = setTimeout(function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')
            ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            : (document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '');

        fetch("{{ route('user.request.alamat') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ latitude: latitude, longitude: longitude })
        })
        .then(function (response) {
            if (!response.ok) throw new Error('Alamat otomatis tidak tersedia.');
            return response.json();
        })
        .then(function (data) {
            if (sequence !== addressLookupSequence) return;
            restoreAddressPlaceholder();
            if (!data.success) throw new Error('Alamat otomatis tidak tersedia.');

            const address = data.address || {};
            let text;

            if (keepTyped && full && full.value.trim()) {
                const typed = full.value.trim();
                const typedParsed = parseAddressText(typed);
                const lower = typed.toLowerCase();
                const extras = [address.kelurahan, address.kecamatan, address.kabupaten, address.provinsi, address.kode_pos]
                    .map(function (s) { return (s || '').toString().trim(); })
                    .filter(function (s) { return s && lower.indexOf(s.toLowerCase()) === -1; });
                text = [typed].concat(extras).join(', ');
                applyParsedParts({
                    jalan: typedParsed.jalan || address.jalan,
                    rt: typedParsed.rt || address.rt,
                    rw: typedParsed.rw || address.rw,
                    kelurahan: address.kelurahan || typedParsed.kelurahan,
                    kecamatan: address.kecamatan || typedParsed.kecamatan,
                    kabupaten: address.kabupaten || typedParsed.kabupaten,
                    provinsi: address.provinsi || typedParsed.provinsi,
                    kode_pos: address.kode_pos || typedParsed.kode_pos
                });
            } else {
                applyParsedParts(address);
                text = composeAddressText(address);
            }

            if (full) full.value = text;
            const lokasiInput = byId('lokasiInput');
            if (lokasiInput) lokasiInput.value = text;
            addressDirty = false;
            lastAddressGeocodeKey = text.toLowerCase(); // cegah pencarian ulang otomatis

            if (status) {
                const providerName = address.provider === 'google_maps' ? 'Google Maps' : 'OpenStreetMap';
                status.innerText = `Alamat lengkap terisi otomatis dari ${providerName}. Anda dapat mengubahnya langsung di kolom ini.`;
            }
        })
        .catch(function () {
            if (sequence !== addressLookupSequence) return;
            restoreAddressPlaceholder();
            if (status) status.innerText = 'Alamat otomatis tidak tersedia. Ketik alamat lengkap pada kolom di atas.';
        });
    }, keepTyped ? 300 : 1200);
}

function clearCurrentRouteLine() {
    if (!routeLine) return;
    if (activeMapEngine === 'google' && typeof routeLine.setMap === 'function') {
        routeLine.setMap(null);
    } else if (activeMapEngine === 'leaflet' && requestMap && typeof requestMap.removeLayer === 'function') {
        requestMap.removeLayer(routeLine);
    }
    routeLine = null;
}

function showMapError(message) {
    const error = document.getElementById('mapError');
    if (error) {
        error.textContent = message;
        error.style.display = 'block';
    }
}

window.gm_authFailure = function () {
    console.warn('Google Maps JavaScript API authentication/billing limitation detected. Switching to Leaflet OpenStreetMap fallback.');
    initializeLeafletFallbackMap();
};

function initializeRequestMap() {
    const lat = Number(document.getElementById('latInput').value);
    const lon = Number(document.getElementById('lonInput').value);
    const homePoint = Number.isFinite(lat) && Number.isFinite(lon) ? [lat, lon] : officePoint;

    if (window.google && window.google.maps && typeof window.google.maps.Map === 'function') {
        try {
            initializeGoogleMap(homePoint);
            // Pantau jika Google Maps mengganti container dengan .gm-err-container karena keterbatasan Billing/API Key
            window.setTimeout(function () {
                const container = document.getElementById('requestMap');
                if (container && container.querySelector('.gm-err-container')) {
                    console.warn('Google Maps error container detected. Switching to Leaflet OpenStreetMap fallback.');
                    initializeLeafletFallbackMap();
                }
            }, 1200);
            return;
        } catch (err) {
            console.warn('Google Maps initialization error, falling back to Leaflet:', err);
        }
    }

    initializeLeafletFallbackMap();
}

function initializeGoogleMap(homePoint) {
    activeMapEngine = 'google';
    const mapEl = document.getElementById('requestMap');
    requestMap = new google.maps.Map(mapEl, {
        center: { lat: homePoint[0], lng: homePoint[1] },
        zoom: 14,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true
    });

    officeMarker = new google.maps.Marker({
        position: { lat: officePoint[0], lng: officePoint[1] },
        map: requestMap,
        title: 'Kantor Pusat TEKSIKA - SMKN 1 Gunung Putri',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 8,
            fillColor: '#22c55e',
            fillOpacity: 1,
            strokeColor: '#166534',
            strokeWeight: 2
        }
    });

    const homeInfo = new google.maps.InfoWindow({
        content: '<strong>Lokasi rumah Anda</strong>'
    });

    homeMarker = new google.maps.Marker({
        position: { lat: homePoint[0], lng: homePoint[1] },
        map: requestMap,
        draggable: true,
        title: 'Lokasi rumah Anda'
    });
    homeMarker.addListener('click', function () {
        homeInfo.open(requestMap, homeMarker);
    });

    requestMap.addListener('click', function (event) {
        if (event.latLng) {
            setHomeLocation({ lat: event.latLng.lat(), lng: event.latLng.lng() });
        }
    });

    homeMarker.addListener('dragend', function (event) {
        if (event.latLng) {
            setHomeLocation({ lat: event.latLng.lat(), lng: event.latLng.lng() });
        }
    });
}

function initializeLeafletFallbackMap() {
    if (activeMapEngine === 'leaflet' && requestMap) return;
    activeMapEngine = 'leaflet';

    const lat = Number(document.getElementById('latInput').value);
    const lon = Number(document.getElementById('lonInput').value);
    const homePoint = Number.isFinite(lat) && Number.isFinite(lon) ? [lat, lon] : officePoint;

    const mapEl = document.getElementById('requestMap');
    if (mapEl) {
        mapEl.innerHTML = '';
        if (mapEl._leaflet_id) {
            mapEl._leaflet_id = null;
        }
    }

    if (!window.L) {
        showMapError('Peta tidak dapat dimuat. Pastikan koneksi internet aktif untuk memuat peta.');
        return;
    }

    requestMap = L.map('requestMap').setView(homePoint, 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        updateWhenZooming: false,
        updateWhenIdle: true,
        keepBuffer: 1,
        attribution: '&copy; OpenStreetMap'
    }).addTo(requestMap);

    officeMarker = L.circleMarker(officePoint, {
        radius: 8,
        color: '#166534',
        fillColor: '#22c55e',
        fillOpacity: 1
    }).bindPopup('<strong>Kantor Pusat TEKSIKA - SMKN 1 Gunung Putri</strong>').addTo(requestMap);

    homeMarker = L.marker(homePoint, { draggable: true })
        .bindPopup('<strong>Lokasi rumah Anda</strong>')
        .addTo(requestMap);

    requestMap.on('click', function (event) {
        setHomeLocation(event.latlng);
    });
    homeMarker.on('dragend', function (event) {
        setHomeLocation(event.target.getLatLng());
    });

    window.setTimeout(function () {
        if (requestMap && typeof requestMap.invalidateSize === 'function') {
            requestMap.invalidateSize();
        }
        if (lastRenderedRoute) {
            renderOsrmRoute(lastRenderedRoute);
        }
    }, 300);
}

function setHomeLocation(point, options = {}) {
    const lat = Number(point.lat).toFixed(7);
    const lon = Number(point.lng || point.lon).toFixed(7);
    clearCurrentRouteLine();
    window.currentRouteDistanceKm = null;
    window.currentRouteUnavailable = false;

    if (activeMapEngine === 'google' && homeMarker && typeof homeMarker.setPosition === 'function') {
        homeMarker.setPosition({ lat: Number(lat), lng: Number(lon) });
    } else if (activeMapEngine === 'leaflet' && homeMarker && typeof homeMarker.setLatLng === 'function') {
        homeMarker.setLatLng([Number(lat), Number(lon)]);
    }

    document.getElementById('latInput').value = lat;
    document.getElementById('lonInput').value = lon;
    document.getElementById('coordDisplay').innerText = `${lat}, ${lon}`;
    markLocationPicked();

    if (options.accuracy && options.accuracy > 0) {
        clearAccuracyCircle();
        const displayRadius = Math.min(Number(options.accuracy), 5000);
        if (activeMapEngine === 'google' && requestMap && window.google && window.google.maps) {
            accuracyCircle = new google.maps.Circle({
                map: requestMap,
                center: { lat: Number(lat), lng: Number(lon) },
                radius: displayRadius,
                fillColor: '#3b82f6',
                fillOpacity: 0.15,
                strokeColor: '#2563eb',
                strokeOpacity: 0.45,
                strokeWeight: 1
            });
        } else if (activeMapEngine === 'leaflet' && requestMap && window.L) {
            accuracyCircle = L.circle([Number(lat), Number(lon)], {
                radius: displayRadius,
                color: '#2563eb',
                weight: 1,
                opacity: 0.45,
                fillColor: '#3b82f6',
                fillOpacity: 0.15
            }).addTo(requestMap);
        }
    } else if (options.clearAccuracy !== false) {
        clearAccuracyCircle();
    }

    // Titik digeser/diklik di peta (bukan hasil pencarian alamat): kosongkan alamat lama,
    // alamat baru akan diisi otomatis dari reverse geocode.
    if (options.clearAddress !== false) {
        clearTimeout(addressGeocodeDebounce);
        addressGeocodeSequence++;
        lastAddressGeocodeKey = '';
        setSearchButtonLoading(false);
        clearAddressFields();
    }
    if (requestMap && options.zoom) {
        if (activeMapEngine === 'google' && typeof requestMap.setCenter === 'function') {
            requestMap.setCenter({ lat: Number(lat), lng: Number(lon) });
            requestMap.setZoom(options.zoom);
        } else if (activeMapEngine === 'leaflet' && typeof requestMap.setView === 'function') {
            requestMap.setView([Number(lat), Number(lon)], options.zoom);
        }
    }
    if (options.lookupAddress !== false) lookupAddressFromCoordinates(lat, lon);
    if (typeof updatePricingBreakdown === 'function') updatePricingBreakdown();
    const routeStatus = document.getElementById('routeStatus');
    if (routeStatus) {
        routeStatus.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Lokasi diperbarui. Menghitung rute perjalanan dari kantor pusat...</span>';
    }
    triggerEstimasi();
}

function renderOsrmRoute(route) {
    lastRenderedRoute = route;
    clearCurrentRouteLine();

    if (activeMapEngine === 'google' && requestMap && window.google && window.google.maps) {
        const googlePath = route.geometry.map(function (coordinate) {
            return { lat: Number(coordinate[1]), lng: Number(coordinate[0]) };
        });
        routeLine = new google.maps.Polyline({
            path: googlePath,
            geodesic: true,
            strokeColor: '#007bff',
            strokeOpacity: 0.85,
            strokeWeight: 5,
            map: requestMap
        });
        if (googlePath.length > 0) {
            const bounds = new google.maps.LatLngBounds();
            googlePath.forEach(function (pt) {
                bounds.extend(pt);
            });
            requestMap.fitBounds(bounds, 24);
        }
    } else if (activeMapEngine === 'leaflet' && requestMap && window.L) {
        const routePoints = route.geometry.map(function (coordinate) {
            return [coordinate[1], coordinate[0]];
        });
        routeLine = L.polyline(routePoints, {
            color: '#007bff',
            weight: 5,
            opacity: 0.85
        }).addTo(requestMap);
        if (routePoints.length > 0) {
            requestMap.fitBounds(routeLine.getBounds(), { padding: [24, 24] });
        }
    }

    const userLat = Number(document.getElementById('latInput').value);
    const userLon = Number(document.getElementById('lonInput').value);
    const straightKm = calculateStraightLineKm(officePoint[0], officePoint[1], userLat, userLon);
    const sourceLabel = route.source === 'google_routes' ? 'Google Maps Compute Routes' : 'OSRM Fallback';

    const routeStatus = document.getElementById('routeStatus');
    if (routeStatus) {
        routeStatus.innerHTML = `<i class="fa-solid fa-route"></i> <span>Rute Jalan (${sourceLabel}): <strong>${route.distance_km} km</strong> (~${route.duration_minutes} menit) &bull; Garis Lurus: <strong>${straightKm} km</strong>. Titik awal: Kantor Pusat TEKSIKA</span>`;
    }
}

// Deteksi Lokasi Pengguna via Geolocation API Presisi Tinggi Perangkat
function detectUserLocation() {
    const btn = document.getElementById('btnGetLocation');
    const notice = document.getElementById('locationNotice');
    const display = document.getElementById('coordDisplay');

    const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    const isHttps = window.location.protocol === 'https:';
    const isInsecureContext = typeof window.isSecureContext !== 'undefined' && !window.isSecureContext && !isLocalhost && !isHttps;

    function renderInsecureNotice() {
        btn.disabled = false;
        btn.innerHTML = '<span>Gunakan lokasi saya</span>';
        if (notice) {
            notice.style.display = 'block';
            const originUrl = window.location.origin;
            notice.innerHTML = `
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px; color:#991b1b; text-align:left;">
                    <div style="display:flex; align-items:center; gap:8px; font-weight:600; margin-bottom:6px;">
                        <i class="fa-solid fa-shield-halved" style="color:#ef4444; font-size:1.1rem;"></i>
                        <span>Browser HP Memerlukan Izin Khusus (Akses HTTP)</span>
                    </div>
                    <div style="font-size:0.83rem; color:#475569; line-height:1.5;">
                        Chrome di Android memblokir chip GPS jika web diakses via IP LAN (HTTP).<br>
                        <strong>Cara cepat mengaktifkan GPS HP Anda (Cukup 1x):</strong>
                        <ol style="margin:6px 0 8px 18px; padding:0;">
                            <li>Buka tab baru di Chrome HP, ketik: <code>chrome://flags</code></li>
                            <li>Cari: <em>Insecure origins treated as secure</em>, pilih <strong>Enabled</strong>.</li>
                            <li>Ketik atau paste URL: <code id="insecureOriginCode">${originUrl}</code> di kotaknya.</li>
                            <li>Tekan tombol <strong>Relaunch</strong> di pojok bawah Chrome HP.</li>
                        </ol>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="navigator.clipboard.writeText('${originUrl}').then(() => alert('URL disalin: ${originUrl}'));" style="font-size:0.78rem; padding:4px 10px;">
                            <i class="fa-regular fa-copy"></i> Salin URL
                        </button>
                    </div>
                    <div style="margin-top:8px; font-size:0.8rem; color:#64748b; font-style:italic;">
                        Atau geser pin merah pada peta langsung ke atap rumah Anda untuk titik akurat.
                    </div>
                </div>
            `;
        }
    }

    if (isInsecureContext) {
        renderInsecureNotice();
        return;
    }

    if (!navigator.geolocation) {
        alert('Browser Anda tidak mendukung deteksi lokasi (Geolocation).');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Membaca GPS...</span>';
    if (notice) {
        notice.style.display = 'block';
        notice.innerHTML = '<i class="fa-solid fa-satellite-dish fa-spin" style="color:#0284c7;"></i> <span>Mengaktifkan sensor GPS perangkat... Mohon tunggu penguncian satelit.</span>';
    }

    let bestPos = null;
    let watchId = null;
    let locked = false;

    function finalizeLock(position) {
        if (locked) return;
        locked = true;
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        const lat = position.coords.latitude.toFixed(7);
        const lon = position.coords.longitude.toFixed(7);
        const accuracy = Math.round(position.coords.accuracy || 0);

        document.getElementById('latInput').value = lat;
        document.getElementById('lonInput').value = lon;
        if (display) display.innerText = `${lat}, ${lon}`;
        setHomeLocation({ lat: Number(lat), lng: Number(lon) }, { zoom: 17, accuracy: accuracy, lookupAddress: true });

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Lokasi Terkunci</span>';
        btn.style.background = '#10b981';

        if (notice) {
            notice.style.display = 'block';
            if (accuracy <= 30) {
                notice.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#16a34a;"></i> <strong>Sensor GPS Perangkat Terkunci Sempurna!</strong> Akurasi sangat tinggi: ±${accuracy} meter.`;
            } else if (accuracy <= 100) {
                notice.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#16a34a;"></i> <strong>GPS Perangkat Berhasil Didapat!</strong> Akurasi presisi: ±${accuracy} meter.`;
            } else if (accuracy <= 1000) {
                notice.innerHTML = `<i class="fa-solid fa-location-dot" style="color:#0284c7;"></i> Sinyal lokasi perangkat didapat (Akurasi: ±${accuracy} m). Anda dapat menggeser pin merah jika ingin mengarahkan tepat ke atap rumah.`;
            } else {
                const km = (accuracy / 1000).toFixed(1);
                notice.innerHTML = `<i class="fa-solid fa-triangle-exclamation" style="color:#d97706;"></i> <strong>Perangkat PC/Laptop ini belum memiliki sensor GPS fisik</strong> (memakai estimasi IP provider: ±${km} km). Untuk titik akurat: silakan <strong>geser pin merah pada peta</strong> atau <strong>cari alamat di kolom pencarian peta</strong>.`;
            }
        }
    }

    const geoOptions = {
        enableHighAccuracy: true,
        timeout: 20000,
        maximumAge: 0
    };

    // Beri waktu sensor hardware GPS hingga 8 detik untuk menyempurnakan akurasi
    const maxWaitTimer = setTimeout(function () {
        if (!locked && bestPos) {
            finalizeLock(bestPos);
        }
    }, 8000);

    watchId = navigator.geolocation.watchPosition(
        function (pos) {
            // Live update koordinat saat sinyal membaik
            if (!bestPos || pos.coords.accuracy < bestPos.coords.accuracy) {
                bestPos = pos;
                const lat = pos.coords.latitude.toFixed(7);
                const lon = pos.coords.longitude.toFixed(7);
                const acc = Math.round(pos.coords.accuracy || 0);

                document.getElementById('latInput').value = lat;
                document.getElementById('lonInput').value = lon;
                if (display) display.innerText = `${lat}, ${lon}`;
                setHomeLocation({ lat: Number(lat), lng: Number(lon) }, { zoom: 17, accuracy: acc, lookupAddress: false });

                if (notice && !locked) {
                    notice.innerHTML = `<i class="fa-solid fa-satellite-dish fa-spin" style="color:#0284c7;"></i> Menerima sinyal sensor perangkat (Akurasi: ±${acc} m)... Menyempurnakan koordinat...`;
                }
            }

            // Jika akurasi hardware GPS sudah sangat tinggi (<= 25 meter), langsung kunci segera!
            if (pos.coords.accuracy <= 25) {
                clearTimeout(maxWaitTimer);
                finalizeLock(pos);
            }
        },
        function (err) {
            clearTimeout(maxWaitTimer);
            if (bestPos) {
                finalizeLock(bestPos);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<span>Gunakan lokasi saya</span>';
                if (notice) {
                    notice.style.display = 'block';
                    const isSecureErr = (err.message && err.message.toLowerCase().includes('secure origin')) || (typeof window.isSecureContext !== 'undefined' && !window.isSecureContext);
                    if (isSecureErr) {
                        renderInsecureNotice();
                    } else {
                        notice.innerHTML = `<i class="fa-solid fa-circle-exclamation" style="color:#ef4444;"></i> Akses GPS perangkat ditolak / tidak tersedia (${err.message}). Pastikan izin lokasi aktif di browser atau geser pin merah pada peta.`;
                    }
                }
            }
        },
        geoOptions
    );
}

// Request AJAX untuk kalkulasi estimasi ke server
let estimasiDebounce = null;
function triggerEstimasi() {
    clearTimeout(estimasiDebounce);
    estimasiDebounce = setTimeout(runEstimasiAjax, 1000);
}

function runEstimasiAjax() {
    const lat = document.getElementById('latInput').value;
    const lon = document.getElementById('lonInput').value;
    if (!lat || !lon) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]') 
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') 
        : (document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '');

    fetch("{{ route('user.request.hitung') }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": csrfToken
        },
        body: JSON.stringify({
            latitude: lat,
            longitude: lon
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            clearCurrentRouteLine();
            window.currentRouteDistanceKm = null;
            window.currentRouteUnavailable = true;
            if (typeof updatePricingBreakdown === 'function') updatePricingBreakdown();
            const straightKm = calculateStraightLineKm(officePoint[0], officePoint[1], Number(lat), Number(lon));
            const routeStatus = document.getElementById('routeStatus');
            if (routeStatus) {
                routeStatus.innerHTML = `<i class="fa-solid fa-circle-info"></i> <span>${data.message || 'Rute jalan tidak dapat dihitung saat ini.'} (Jarak Garis Lurus: <strong>${straightKm} km</strong>)</span>`;
            }
            return;
        }
        window.currentRouteDistanceKm = Number(data.route.distance_km);
        window.currentRouteUnavailable = false;
        if (typeof updatePricingBreakdown === 'function') updatePricingBreakdown();
        renderOsrmRoute(data.route);
    })
    .catch(err => {
        console.error("Error estimasi:", err);
        window.currentRouteDistanceKm = null;
        window.currentRouteUnavailable = true;
        if (typeof updatePricingBreakdown === 'function') updatePricingBreakdown();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initializeRequestMap();
    triggerEstimasi();

    // Kolom pencarian peta: Enter = Cari
    const place = byId('placeSearch');
    if (place) {
        place.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchPlaceNow();
            }
        });
    }

    const full = byId('alamatLengkap');
    if (full) {
        full.dataset.defaultPlaceholder = full.placeholder;

        // Pengguna mengetik sendiri: batalkan isian otomatis yang masih menunggu agar teks tidak tertimpa
        full.addEventListener('input', function () {
            addressDirty = true;
            clearTimeout(addressLookupDebounce);
            addressLookupSequence++;
            restoreAddressPlaceholder();
        });

        // Enter = pindahkan pin ke alamat yang diketik (bukan kirim form)
        full.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchFromAddressField();
            }
        });

        const initLat = byId('latInput') ? byId('latInput').value : '';
        const initLon = byId('lonInput') ? byId('lonInput').value : '';

        if (full.value.trim()) {
            // Alamat dari old() setelah validasi gagal: pastikan field tersembunyi terisi
            if (!(byId('alamatJalan') && byId('alamatJalan').value.trim())) {
                applyParsedParts(parseAddressText(full.value));
            }
            const lokasiInput = byId('lokasiInput');
            if (lokasiInput) lokasiInput.value = full.value.trim();
        } else if (full.dataset.hasSavedCoords === '1' && initLat && initLon) {
            // Hanya isi otomatis jika koordinat berasal dari profil / input sebelumnya (bukan titik default kantor)
            lookupAddressFromCoordinates(initLat, initLon);
        }
    }

    window.addEventListener('resize', function() {
        if (activeMapEngine === 'leaflet' && requestMap && typeof requestMap.invalidateSize === 'function') {
            requestMap.invalidateSize();
        } else if (activeMapEngine === 'google' && requestMap && window.google && window.google.maps) {
            google.maps.event.trigger(requestMap, 'resize');
        }
    });
});
</script>