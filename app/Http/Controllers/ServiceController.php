<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ServiceController extends Controller
{
    /**
     * Dashboard admin.
     * Jika index() lama kamu sudah berjalan, PERTAHANKAN isinya.
     * Versi ini hanya contoh yang cocok dengan variabel di view.
     * Sesuaikan nama view dengan file blade kamu.
     */
    public function index()
    {
        $services      = Service::latest()->get();
        $totalBooking  = \App\Models\Request::count();
        $activeWorkers = \App\Models\Worker::count();

        return view('dashboard_admin', compact('services', 'totalBooking', 'activeWorkers'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());

        $data['status'] = $data['status'] ?? 'aktif';

        if ($request->hasFile('gambar')) {
            $data['gambar'] = $this->saveImage($request);
        } else {
            unset($data['gambar']);
        }

        Service::create($data);

        return response()->json(['success' => true, 'message' => 'Layanan berhasil ditambahkan.']);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate($this->rules(true), $this->messages());

        if ($request->hasFile('gambar')) {
            $this->deleteImage($service->gambar);
            $data['gambar'] = $this->saveImage($request);
        } else {
            unset($data['gambar']); // jangan menimpa gambar lama dengan null
        }

        $service->update($data);

        return response()->json(['success' => true, 'message' => 'Layanan berhasil diperbarui.']);
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->deleteImage($service->gambar);
        $service->delete();

        return response()->json(['success' => true, 'message' => 'Layanan berhasil dihapus.']);
    }

    /* ----------------------------------------------------------------- */

    private function rules(bool $isUpdate = false): array
    {
        return [
            'nama'      => ['required', 'string', 'max:255'],
            'harga_min' => ['required', 'numeric', 'min:0'],
            'harga_max' => ['required', 'numeric', 'gte:harga_min'],
            'status'    => [$isUpdate ? 'required' : 'nullable', 'in:aktif,nonaktif'],
            'deskripsi' => ['nullable', 'string'],
            'gambar'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function messages(): array
    {
        return [
            'nama.required'      => 'Nama layanan wajib diisi.',
            'harga_min.required' => 'Harga minimum wajib diisi.',
            'harga_max.required' => 'Harga maksimum wajib diisi.',
            'harga_max.gte'      => 'Harga maksimum tidak boleh lebih kecil dari harga minimum.',
            'gambar.image'       => 'File harus berupa gambar.',
            'gambar.mimes'       => 'Format gambar harus JPG, PNG, atau WEBP.',
            'gambar.max'         => 'Ukuran gambar maksimal 2MB.',
            'gambar.uploaded'    => 'Gambar gagal diunggah. Ukuran file melebihi batas server (cek upload_max_filesize di php.ini).',
        ];
    }

    private function saveImage(Request $request): string
    {
        $dir = public_path('uploads/services');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $file = $request->file('gambar');
        $name = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $file->move($dir, $name);

        return $name;
    }

    private function deleteImage(?string $filename): void
    {
        if ($filename) {
            File::delete(public_path('uploads/services/' . $filename));
        }
    }
}