<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — TEKSIKA</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS & Icons -->
    <link rel="stylesheet" href="{{ asset('css/dashboard_admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/profile-modal.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .form-error-box {
            display: none;
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 14px;
            font-size: 0.88rem;
        }
        .form-error-box ul { margin: 0; padding-left: 18px; }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo-wrap">
            <img src="{{ asset('images/logo-putih.png') }}" alt="Logo TEKSIKA" class="sidebar-logo">
        </div>

        <a href="{{ route('admin.home') }}" class="menu-item active">
            <img src="{{ asset('images/home.png') }}" alt="logo" class="icon">
            <span class="menu-text">Beranda</span>
        </a>
        <a href="{{ route('booking') }}" class="menu-item">
            <img src="{{ asset('images/booking.png') }}" alt="logo" class="icon">
            <span class="menu-text">Booking</span>
        </a>
        <a href="{{ route('transaksi') }}" class="menu-item">
            <img src="{{ asset('images/pembayaran.png') }}" alt="logo" class="icon">
            <span class="menu-text">Pembayaran</span>
        </a>
        <a href="{{ route('worker') }}" class="menu-item">
            <img src="{{ asset('images/worker.png') }}" alt="logo" class="icon">
            <span class="menu-text">Worker</span>
        </a>
        <a href="{{ route('admin.pricing.index') }}" class="menu-item">
            <i class="fa-solid fa-sliders"></i>
            <span class="menu-text">Aturan Tarif</span>
        </a>
        <a href="{{ route('chat') }}" class="menu-item">
            <img src="{{ asset('images/chat.png') }}" alt="logo" class="icon">
            <span class="menu-text">Chat</span>
        </a>

        <!-- Logout Tunggal Admin -->
        <div class="logout">
            <a href="javascript:void(0)" onclick="openLogoutModal()" class="menu-item">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="menu-text">Keluar</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main" id="main">
        <!-- Header -->
        <div class="header">
            <div class="menu-toggle" id="menuToggle">
                <i class="fa-solid fa-bars"></i>
            </div>
            <input type="text" id="searchServices" placeholder="Cari layanan...">

            <div class="user-info" style="position:relative;">
                @php
                    $pendingBookingCount = \App\Models\Request::where('status_admin', 'belum_diterima')->count();
                    $pendingPaymentsCount = \App\Models\Pembayaran::where('status', 'pending')->count();
                    $notifTotalCount = $pendingBookingCount + $pendingPaymentsCount;
                    $notifBookings = \App\Models\Request::where('status_admin', 'belum_diterima')->orderBy('created_at', 'desc')->take(5)->get();
                    $notifPayments = \App\Models\Pembayaran::where('status', 'pending')->orderBy('created_at', 'desc')->take(5)->get();
                    $currentAdminUser = Auth::guard('admin')->user() ?? auth()->user();
                @endphp

                <!-- Notifikasi Bell -->
                <div id="notifBell" class="notif-bell" title="Notifikasi Masuk">
                    <i class="fa-solid fa-bell"></i>
                    @if($notifTotalCount > 0)
                        <span class="notif-badge">{{ $notifTotalCount }}</span>
                    @endif
                </div>

                <!-- Dropdown Notifikasi -->
                <div id="notifDropdown" class="notif-dropdown">
                    <div style="padding:14px 18px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between;">
                        <strong style="color:#0f172a; font-size:0.95rem;">Notifikasi Baru</strong>
                        <span style="font-size:0.75rem; background:#eff6ff; color:#1d4ed8; font-weight:700; padding:3px 8px; border-radius:999px;">
                            {{ $notifTotalCount }} Menunggu
                        </span>
                    </div>
                    <div style="padding:12px 16px;">
                        <div style="font-size:0.8rem; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:8px;">Booking Masuk</div>
                        @forelse($notifBookings as $b)
                            <a href="{{ route('booking') }}" style="display:block; padding:10px 12px; border-radius:10px; text-decoration:none; color:#0f172a; background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:8px; transition:all 0.2s;">
                                <div style="font-size:0.9rem; font-weight:700;">{{ $b->kode_booking ?? 'BK-' . str_pad($b->id_request,3,'0',STR_PAD_LEFT) }}</div>
                                <div style="font-size:0.82rem; color:#475569;">{{ $b->layanan ?? 'Layanan' }} &bull; {{ $b->lokasi ?? '-' }}</div>
                                <div style="font-size:0.76rem; color:#94a3b8; margin-top:2px;">{{ $b->created_at?->diffForHumans() }}</div>
                            </a>
                        @empty
                            <div style="font-size:0.84rem; color:#94a3b8; padding:6px 0;">Tidak ada booking baru.</div>
                        @endforelse
                    </div>
                    <div style="padding:12px 16px; border-top:1px solid #f1f5f9;">
                        <div style="font-size:0.8rem; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:8px;">Pembayaran Masuk</div>
                        @forelse($notifPayments as $p)
                            <a href="{{ route('transaksi') }}" style="display:block; padding:10px 12px; border-radius:10px; text-decoration:none; color:#0f172a; background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:8px; transition:all 0.2s;">
                                <div style="font-size:0.9rem; font-weight:700;">{{ $p->kode_pembayaran }}</div>
                                <div style="font-size:0.82rem; color:#475569;">Rp {{ number_format($p->jumlah,0,',','.') }} &bull; {{ $p->metode_pembayaran }}</div>
                                <div style="font-size:0.76rem; color:#94a3b8; margin-top:2px;">{{ $p->created_at?->diffForHumans() }}</div>
                            </a>
                        @empty
                            <div style="font-size:0.84rem; color:#94a3b8; padding:6px 0;">Tidak ada pembayaran baru.</div>
                        @endforelse
                    </div>
                    <div style="padding:12px 16px; border-top:1px solid #e2e8f0; display:flex; gap:10px; justify-content:flex-end;">
                        <a href="{{ route('booking') }}" style="font-size:0.85rem; font-weight:700; color:#007bff; text-decoration:none;">Semua Booking &rarr;</a>
                        <a href="{{ route('transaksi') }}" style="font-size:0.85rem; font-weight:700; color:#007bff; text-decoration:none;">Semua Pembayaran &rarr;</a>
                    </div>
                </div>

                <!-- Header Profile Pill -->
                <div class="header-profile-pill" onclick="openAdminProfileModal()" title="Profil Akun Administrator">
                    <div class="admin-avatar-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <span>{{ $currentAdminUser->name ?? 'Admin' }}</span>
                    <i class="fa-solid fa-chevron-down profile-chevron"></i>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="admin-content-wrapper">
            <!-- Welcome Banner -->
            <div class="welcome">
                <div>
                    <h1><i class="fa-solid fa-shield-halved" style="color:#007bff; margin-right:8px;"></i> Panel Administrator</h1>
                    <p>Selamat datang kembali! Pantau ringkasan permintaan layanan, transaksi, dan kesiapan mitra hari ini.</p>
                </div>
                <div class="welcome-badge">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Sistem Aktif &amp; Terverifikasi</span>
                </div>
            </div>

            <!-- Stats Bento Grid -->
            <div class="stats">
                <div class="card blue">
                    <div>
                        <p class="label">Total Booking</p>
                        <h2>{{ $totalBooking ?? 0 }}</h2>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                </div>

                <div class="card purple">
                    <div>
                        <p class="label">Mitra Siap Kerja</p>
                        <h2>{{ $activeWorkers ?? 0 }}</h2>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                </div>

                <div class="card green">
                    <div>
                        <p class="label">Booking Menunggu</p>
                        <h2>{{ $pendingBookingCount }}</h2>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>

                <div class="card orange">
                    <div>
                        <p class="label">Pembayaran Pending</p>
                        <h2>{{ $pendingPaymentsCount }}</h2>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                </div>
            </div>

            @php($services = isset($services) ? $services : collect())

            <!-- Section Layanan Tersedia -->
            <div class="services-header-row">
                <h3><i class="fa-solid fa-wrench" style="color:#007bff; margin-right:8px;"></i> Kategori Layanan Tersedia</h3>
                <button type="button" class="btn-add" onclick="openCreatePopup()">
                    <i class="fa-solid fa-plus"></i>
                    <span>Tambah Layanan</span>
                </button>
            </div>

            <div class="services-grid">
                @forelse($services as $service)
                    <div class="service-card" data-id="{{ $service->id }}">
                        <div class="service-card-row">
                            <div class="service-icon">
                                @if(!empty($service->gambar))
                                    <img src="{{ asset('uploads/services/'.$service->gambar) }}" alt="{{ $service->nama }}" onerror="this.onerror=null;this.src='{{ asset('images/listrik.png') }}';">
                                @elseif(strtolower($service->nama) === 'listrik')
                                    <img src="{{ asset('images/listrik.png') }}" alt="icon listrik">
                                @else
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                @endif
                            </div>
                            <div class="service-info">
                                <h4>{{ $service->nama }}</h4>
                                <p>{{ $service->deskripsi }}</p>
                                <span class="service-price-tag">
                                    Rp {{ number_format($service->harga_min,0,',','.') }} - Rp {{ number_format($service->harga_max,0,',','.') }}
                                </span>
                                <span class="service-status {{ $service->status == 'aktif' ? 'status-aktif' : 'status-nonaktif' }}">
                                    <i class="fa-solid {{ $service->status == 'aktif' ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                                    {{ ucfirst($service->status) }}
                                </span>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button type="button"
                                    class="action-pill-edit edit-btn"
                                    data-id="{{ $service->id }}"
                                    data-nama="{{ $service->nama }}"
                                    data-deskripsi="{{ $service->deskripsi }}"
                                    data-harga-min="{{ (int) $service->harga_min }}"
                                    data-harga-max="{{ (int) $service->harga_max }}"
                                    data-status="{{ $service->status }}"
                                    data-gambar="{{ !empty($service->gambar) ? asset('uploads/services/'.$service->gambar) : '' }}"
                                    title="Edit Layanan">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>Edit</span>
                            </button>
                            <button type="button" class="btn-reject delete-btn" data-id="{{ $service->id }}" title="Hapus Layanan">
                                <i class="fa-solid fa-trash"></i>
                                <span>Hapus</span>
                            </button>
                        </div>
                    </div>
                @empty
                    <div style="grid-column: 1 / -1; padding:32px 20px; background:#fff; border:1.5px dashed #cbd5e1; border-radius:18px; text-align:center; color:#64748b;">
                        <i class="fa-solid fa-box-open" style="font-size:2.5rem; color:#94a3b8; margin-bottom:12px;"></i>
                        <p style="font-weight:600; font-size:1rem; margin:0 0 6px 0;">Belum Ada Layanan Terdaftar</p>
                        <p style="font-size:0.86rem; margin:0 0 16px 0;">Klik tombol Tambah Layanan untuk mendaftarkan layanan baru.</p>
                        <button type="button" class="btn-add" onclick="openCreatePopup()">
                            <i class="fa-solid fa-plus"></i> Tambah Layanan Pertama
                        </button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Popup Tambah Layanan -->
    <div class="popup-overlay" id="createPopup" style="display:none;" onclick="if(event.target===this) closeCreatePopup();">
      <div class="popup-content popup-large">
          <div class="popup-header-modern">
              <div class="popup-header-info">
                  <div class="popup-icon-bubble">
                      <i class="fa-solid fa-plus-circle"></i>
                  </div>
                  <div>
                      <h3 class="popup-header-title">Tambah Layanan Baru</h3>
                      <p class="popup-header-subtitle">Daftarkan kategori layanan perbaikan untuk pemesanan pelanggan.</p>
                  </div>
              </div>
              <button type="button" class="popup-close-btn" onclick="closeCreatePopup()" title="Tutup">
                  <i class="fa-solid fa-xmark"></i>
              </button>
          </div>

          <div id="createErrorBox" class="form-error-box"></div>

          <form id="createForm" action="{{ route('services.store') }}" method="POST" enctype="multipart/form-data" novalidate>
              @csrf
              <div class="form-grid-2col">
                  <!-- Kolom Kiri -->
                  <div>
                      <div style="margin-bottom:14px;">
                          <label for="create_nama"><i class="fa-solid fa-tag" style="color:#007bff; margin-right:4px;"></i> Nama Layanan <span style="color:#ef4444;">*</span></label>
                          <input type="text" id="create_nama" name="nama" placeholder="Contoh: Perbaikan Atap Bocor" required>
                      </div>

                      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                          <div>
                              <label for="create_harga_min"><i class="fa-solid fa-money-bill-wave" style="color:#10b981; margin-right:4px;"></i> Harga Min (Rp) <span style="color:#ef4444;">*</span></label>
                              <input type="number" id="create_harga_min" name="harga_min" placeholder="100000" min="0" required>
                          </div>
                          <div>
                              <label for="create_harga_max"><i class="fa-solid fa-money-bill-wave" style="color:#10b981; margin-right:4px;"></i> Harga Max (Rp) <span style="color:#ef4444;">*</span></label>
                              <input type="number" id="create_harga_max" name="harga_max" placeholder="300000" min="0" required>
                          </div>
                      </div>

                      <div>
                          <label for="create_status"><i class="fa-solid fa-toggle-on" style="color:#007bff; margin-right:4px;"></i> Status Layanan</label>
                          <select id="create_status" name="status">
                              <option value="aktif">Aktif (Dapat dipilih user)</option>
                              <option value="nonaktif">Nonaktif (Disembunyikan)</option>
                          </select>
                      </div>
                  </div>

                  <!-- Kolom Kanan -->
                  <div>
                      <div style="margin-bottom:14px;">
                          <label for="create_gambar"><i class="fa-solid fa-image" style="color:#007bff; margin-right:4px;"></i> Gambar / Icon Layanan (Opsional)</label>
                          <div class="file-upload-card">
                              <div class="file-preview-avatar" id="createServicePreviewWrap">
                                  <img id="createServicePreviewImg" src="" alt="Preview" style="display:none;">
                                  <i id="createServiceDefaultIcon" class="fa-solid fa-image"></i>
                              </div>
                              <div class="file-upload-details">
                                  <input type="file" id="create_gambar" name="gambar" accept="image/png,image/jpeg,image/webp" onchange="previewServiceImage(this, 'createServicePreviewImg', 'createServiceDefaultIcon')">
                                  <div style="font-size:0.76rem; color:#64748b; margin-top:5px;">
                                      Format JPG, PNG, WEBP (Maksimal 2MB).
                                  </div>
                              </div>
                          </div>
                      </div>

                      <div>
                          <label for="create_deskripsi"><i class="fa-solid fa-align-left" style="color:#007bff; margin-right:4px;"></i> Deskripsi Singkat</label>
                          <textarea id="create_deskripsi" name="deskripsi" placeholder="Tuliskan spesifikasi umum perbaikan..." rows="3"></textarea>
                      </div>
                  </div>
              </div>

              <div class="popup-actions">
                  <button type="button" class="btn-cancel" onclick="closeCreatePopup()">Batal</button>
                  <button type="submit" class="btn-primary" id="createSubmitBtn">
                      <i class="fa-solid fa-plus"></i> Tambah Layanan
                  </button>
              </div>
          </form>
      </div>
    </div>

    <!-- Popup Edit Layanan -->
    <div class="popup-overlay" id="popup" style="display:none;" onclick="if(event.target===this) closeEditPopup();">
      <div class="popup-content popup-large">
          <div class="popup-header-modern">
              <div class="popup-header-info">
                  <div class="popup-icon-bubble" style="background:#faf5ff; color:#9333ea; border-color:#e9d5ff;">
                      <i class="fa-solid fa-pen-to-square"></i>
                  </div>
                  <div>
                      <h3 class="popup-header-title">Edit Kategori Layanan</h3>
                      <p class="popup-header-subtitle">Perbarui nama, rentang harga dasar, status, atau gambar layanan.</p>
                  </div>
              </div>
              <button type="button" class="popup-close-btn" onclick="closeEditPopup()" title="Tutup">
                  <i class="fa-solid fa-xmark"></i>
              </button>
          </div>

          <div id="editErrorBox" class="form-error-box"></div>

          <form id="editForm" enctype="multipart/form-data" novalidate>
              <input type="hidden" id="editId">
              <input type="hidden" name="_method" value="PUT">

              <div class="form-grid-2col">
                  <!-- Kolom Kiri -->
                  <div>
                      <div style="margin-bottom:14px;">
                          <label for="namaLayanan"><i class="fa-solid fa-tag" style="color:#007bff; margin-right:4px;"></i> Nama Layanan <span style="color:#ef4444;">*</span></label>
                          <input type="text" id="namaLayanan" name="nama" required>
                      </div>

                      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                          <div>
                              <label for="editHargaMin"><i class="fa-solid fa-money-bill-wave" style="color:#10b981; margin-right:4px;"></i> Harga Min (Rp) <span style="color:#ef4444;">*</span></label>
                              <input type="number" id="editHargaMin" name="harga_min" placeholder="Harga Min (Rp)" min="0" required>
                          </div>
                          <div>
                              <label for="editHargaMax"><i class="fa-solid fa-money-bill-wave" style="color:#10b981; margin-right:4px;"></i> Harga Max (Rp) <span style="color:#ef4444;">*</span></label>
                              <input type="number" id="editHargaMax" name="harga_max" placeholder="Harga Max (Rp)" min="0" required>
                          </div>
                      </div>

                      <div>
                          <label for="status"><i class="fa-solid fa-toggle-on" style="color:#007bff; margin-right:4px;"></i> Status Layanan</label>
                          <select id="status" name="status">
                              <option value="aktif">Aktif (Dapat dipilih user)</option>
                              <option value="nonaktif">Tidak Aktif (Disembunyikan)</option>
                          </select>
                      </div>
                  </div>

                  <!-- Kolom Kanan -->
                  <div>
                      <div style="margin-bottom:14px;">
                          <label for="editGambar"><i class="fa-solid fa-image" style="color:#007bff; margin-right:4px;"></i> Ganti Gambar / Icon (Opsional)</label>
                          <div class="file-upload-card">
                              <div class="file-preview-avatar" id="editServicePreviewWrap">
                                  <img id="editServicePreviewImg" src="" alt="Icon Layanan" style="display:none;">
                                  <i id="editServiceDefaultIcon" class="fa-solid fa-image"></i>
                              </div>
                              <div class="file-upload-details">
                                  <input type="file" id="editGambar" name="gambar" accept="image/png,image/jpeg,image/webp" onchange="previewServiceImage(this, 'editServicePreviewImg', 'editServiceDefaultIcon')">
                                  <div style="font-size:0.76rem; color:#64748b; margin-top:5px;">
                                      Kosongkan jika tidak ingin mengubah icon saat ini (Maksimal 2MB).
                                  </div>
                              </div>
                          </div>
                      </div>

                      <div>
                          <label for="deskripsi"><i class="fa-solid fa-align-left" style="color:#007bff; margin-right:4px;"></i> Deskripsi Singkat</label>
                          <textarea id="deskripsi" name="deskripsi" rows="3"></textarea>
                      </div>
                  </div>
              </div>

              <div class="popup-actions">
                  <button type="button" class="btn-cancel" onclick="closeEditPopup()">Batal</button>
                  <button type="submit" class="btn-primary" id="editSubmitBtn">
                      <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                  </button>
              </div>
          </form>
      </div>
    </div>

    <!-- Admin Profile Modal -->
    @include('admin._profile_modal')

    <!-- Modal Logout Tunggal Admin -->
    @include('admin._logout_modal')

    <!-- Scripts -->
    <script src="{{ asset('js/sidebar.js') }}"></script>

    <script>
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
    const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2MB, sama dengan validasi di controller

    /* ---------- Modal Profil & Logout ---------- */
    function openAdminProfileModal(){
        var el = document.getElementById('adminProfileModal');
        if(el){ el.style.display = 'flex'; }
    }
    function closeAdminProfileModal(){
        var el = document.getElementById('adminProfileModal');
        if(el){ el.style.display = 'none'; }
    }
    function openLogoutModal(){
        var el = document.getElementById('logoutModal');
        if(el){ el.style.display = 'flex'; }
    }
    function closeLogoutModal(){
        var el = document.getElementById('logoutModal');
        if(el){ el.style.display = 'none'; }
    }
    function confirmLogout(){
        window.location.href = "{{ route('logout') }}";
    }

    /* ---------- Helper ---------- */
    function showFormError(box, messages){
        if(!box) return;
        const list = Array.isArray(messages) ? messages : [messages];
        box.innerHTML = "";
        const ul = document.createElement("ul");
        list.forEach(m => {
            const li = document.createElement("li");
            li.textContent = m;
            ul.appendChild(li);
        });
        box.appendChild(ul);
        box.style.display = "block";
    }
    function hideFormError(box){
        if(box){ box.style.display = "none"; box.innerHTML = ""; }
    }

    // Kirim form lewat fetch, tangani 419 / 422 / error server
    async function submitServiceForm({ url, formData, button, errorBox }){
        hideFormError(errorBox);
        button.disabled = true;
        try {
            const res = await fetch(url, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": token,
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                },
                body: formData
            });

            if(res.status === 419){
                showFormError(errorBox, "Sesi habis. Halaman akan dimuat ulang, silakan coba lagi.");
                setTimeout(() => location.reload(), 1500);
                return;
            }

            let data = {};
            try { data = await res.json(); } catch(e) {}

            if(res.ok && data.success){
                location.reload();
                return;
            }
            if(res.status === 422 && data.errors){
                showFormError(errorBox, Object.values(data.errors).flat());
                return;
            }
            showFormError(errorBox, data.message || ("Gagal menyimpan (kode " + res.status + "). Cek storage/logs/laravel.log."));
        } catch(err){
            showFormError(errorBox, "Gagal terhubung ke server. Pastikan php artisan serve masih berjalan.");
        } finally {
            button.disabled = false;
        }
    }

    function imageTooLarge(inputEl){
        const f = inputEl.files && inputEl.files[0];
        return f && f.size > MAX_IMAGE_BYTES;
    }

    /* ---------- Live preview gambar ---------- */
    function previewServiceImage(input, imgId, iconId){
        if(input.files && input.files[0]){
            const reader = new FileReader();
            reader.onload = function(e){
                const img = document.getElementById(imgId);
                const icon = document.getElementById(iconId);
                if(img){ img.src = e.target.result; img.style.display = 'block'; }
                if(icon){ icon.style.display = 'none'; }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    /* ---------- CREATE ---------- */
    function openCreatePopup(){
        document.getElementById("createForm").reset();
        hideFormError(document.getElementById("createErrorBox"));
        const img = document.getElementById("createServicePreviewImg");
        const icon = document.getElementById("createServiceDefaultIcon");
        if(img){ img.src = ""; img.style.display = "none"; }
        if(icon){ icon.style.display = "block"; }
        document.getElementById("createPopup").style.display = "flex";
    }
    function closeCreatePopup(){
        document.getElementById("createPopup").style.display = "none";
    }

    document.getElementById("createForm").addEventListener("submit", e => {
        e.preventDefault();
        const form = e.target;
        const errorBox = document.getElementById("createErrorBox");

        const min = parseFloat(document.getElementById("create_harga_min").value);
        const max = parseFloat(document.getElementById("create_harga_max").value);
        const errors = [];
        if(!form.nama.value.trim()) errors.push("Nama layanan wajib diisi.");
        if(isNaN(min)) errors.push("Harga minimum wajib diisi.");
        if(isNaN(max)) errors.push("Harga maksimum wajib diisi.");
        if(!isNaN(min) && !isNaN(max) && max < min) errors.push("Harga maksimum tidak boleh lebih kecil dari harga minimum.");
        if(imageTooLarge(document.getElementById("create_gambar"))) errors.push("Ukuran gambar maksimal 2MB. Pilih gambar yang lebih kecil.");
        if(errors.length){ showFormError(errorBox, errors); return; }

        submitServiceForm({
            url: form.action,
            formData: new FormData(form),
            button: document.getElementById("createSubmitBtn"),
            errorBox: errorBox
        });
    });

    /* ---------- EDIT ---------- */
    function closeEditPopup(){
        document.getElementById("popup").style.display = "none";
    }

    document.querySelectorAll(".edit-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            hideFormError(document.getElementById("editErrorBox"));
            document.getElementById("editId").value = btn.dataset.id;
            document.getElementById("namaLayanan").value = btn.dataset.nama || "";
            document.getElementById("deskripsi").value = btn.dataset.deskripsi || "";
            document.getElementById("editHargaMin").value = btn.dataset.hargaMin || "0";
            document.getElementById("editHargaMax").value = btn.dataset.hargaMax || "0";
            document.getElementById("status").value = btn.dataset.status === "aktif" ? "aktif" : "nonaktif";
            document.getElementById("editGambar").value = "";

            const editImg = document.getElementById("editServicePreviewImg");
            const editIcon = document.getElementById("editServiceDefaultIcon");
            if(btn.dataset.gambar){
                editImg.src = btn.dataset.gambar;
                editImg.style.display = "block";
                editIcon.style.display = "none";
            } else {
                editImg.src = "";
                editImg.style.display = "none";
                editIcon.style.display = "block";
            }

            document.getElementById("popup").style.display = "flex";
        });
    });

    document.getElementById("editForm").addEventListener("submit", e => {
        e.preventDefault();
        const errorBox = document.getElementById("editErrorBox");
        const id = document.getElementById("editId").value;

        const min = parseFloat(document.getElementById("editHargaMin").value);
        const max = parseFloat(document.getElementById("editHargaMax").value);
        const errors = [];
        if(!document.getElementById("namaLayanan").value.trim()) errors.push("Nama layanan wajib diisi.");
        if(isNaN(min) || isNaN(max)) errors.push("Harga minimum dan maksimum wajib diisi.");
        if(!isNaN(min) && !isNaN(max) && max < min) errors.push("Harga maksimum tidak boleh lebih kecil dari harga minimum.");
        if(imageTooLarge(document.getElementById("editGambar"))) errors.push("Ukuran gambar maksimal 2MB. Pilih gambar yang lebih kecil.");
        if(errors.length){ showFormError(errorBox, errors); return; }

        // POST + _method=PUT (method spoofing) agar file bisa ikut terkirim
        submitServiceForm({
            url: "{{ url('/services') }}/" + id,
            formData: new FormData(e.target),
            button: document.getElementById("editSubmitBtn"),
            errorBox: errorBox
        });
    });

    /* ---------- DELETE ---------- */
    document.querySelectorAll(".delete-btn").forEach(btn => {
        btn.addEventListener("click", async () => {
            if(!confirm("Yakin ingin menghapus layanan ini dari sistem?")) return;
            try {
                const res = await fetch("{{ url('/services') }}/" + btn.dataset.id, {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": token,
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    }
                });
                if(res.status === 419){ alert("Sesi habis. Halaman akan dimuat ulang."); location.reload(); return; }
                let data = {};
                try { data = await res.json(); } catch(e) {}
                if(res.ok && data.success){ location.reload(); }
                else { alert(data.message || "Gagal menghapus layanan (kode " + res.status + ")."); }
            } catch(err){
                alert("Gagal terhubung ke server.");
            }
        });
    });

    /* ---------- Tutup popup dengan tombol Esc ---------- */
    document.addEventListener("keydown", e => {
        if(e.key === "Escape"){
            closeCreatePopup();
            closeEditPopup();
        }
    });

    /* ---------- Pencarian layanan (client-side) ---------- */
    (function(){
        const input = document.getElementById('searchServices');
        const cards = Array.from(document.querySelectorAll('.service-card'));
        if(!input || cards.length === 0) return;
        input.addEventListener('input', function(){
            const q = this.value.trim().toLowerCase();
            cards.forEach(card => {
                const name = card.querySelector('h4')?.innerText.trim().toLowerCase() || '';
                card.style.display = name.includes(q) ? '' : 'none';
            });
        });
    })();

    /* ---------- Dropdown notifikasi ---------- */
    (function(){
        const bell = document.getElementById('notifBell');
        const dd = document.getElementById('notifDropdown');
        if(!bell || !dd) return;
        function closeDD(){
            dd.style.display = 'none';
            document.removeEventListener('click', onOutsideClick);
            document.removeEventListener('keydown', onEsc);
        }
        function onOutsideClick(e){
            if(!dd.contains(e.target) && !bell.contains(e.target)){
                closeDD();
            }
        }
        function onEsc(e){ if(e.key === 'Escape'){ closeDD(); } }
        bell.addEventListener('click', function(e){
            e.stopPropagation();
            const show = dd.style.display !== 'block';
            if(show){
                dd.style.display = 'block';
                setTimeout(() => {
                    document.addEventListener('click', onOutsideClick);
                    document.addEventListener('keydown', onEsc);
                }, 0);
            } else {
                closeDD();
            }
        });
    })();
    </script>
</body>
</html>