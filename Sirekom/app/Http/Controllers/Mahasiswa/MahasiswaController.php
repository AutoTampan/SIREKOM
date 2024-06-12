<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Models\Lomba;
use App\Models\Peserta;
use App\Models\Mahasiswa;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class MahasiswaController extends Controller
{
    public function index()
    {
        $lomba = Lomba::all();
        return view('app.mahasiswa.data-lomba', [
            'lombas' => $lomba
        ]);
    }

    public function edit()
    {
        // add view edit
    }

    public function update(Request $request, Mahasiswa $mahasiswa)
    {
        $validatedData = $request->validate([
            'username' => 'required',
            'password' => 'required',
            'namaMahasiswa' => 'required',
            'email' => 'required|email|unique:mahasiswas,email,' . $mahasiswa->id,
            'nim' => 'required|digits:10|unique:mahasiswas,nim,' . $mahasiswa->id,
            'jurusan' => 'required',
            'angkatan' => 'required',
            'noHP' => 'required|digits_between:11,12|unique:mahasiswas,nohp,' . $mahasiswa->id,
            'fotoProfile' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $mahasiswa = Mahasiswa::find($mahasiswa->id);

        // Mengecek Apakah Terdapat File foto yang baru
        if ($request->hasFile('fotoProfile')) {
            // Menghapus foto lama apabila terdapat foto yang baru
            if ($mahasiswa->foto) {
                Storage::delete('public/assets/img/profile' . $mahasiswa->fotoProfile);
            }

            // Menyimpan foto yang baru
            $file = $request->file('fotoProfile');
            $randomString = Str::random(10);
            $name_file = $randomString . "_" . $file->getClientOriginalName();
            $file->storeAs('public/assets/img/profile', $name_file);
            $validatedData['fotoProfile'] = $name_file;
        }

        $mahasiswa->update($validatedData);
        return redirect('/mahasiswa/lomba');
    }
    public function show(Lomba $lomba)
    {
        // $tasks = Task::where('idLomba', $lomba->id)->get();

        // return view("app.mahasiswa.detailLomba", [
        //     "lomba" => $lomba,
        //     "tasks" => $tasks
        // ]);
        $tasks = DB::table('task')
            ->join('lombas', 'task.idLomba', '=', 'lombas.id')
            ->select(
                'task.namaTask',
                'task.tipe',
                'task.deskripsiTask',
                'task.deadlineTask',
                'task.lampiran',
                'lombas.namaLomba',
                'lombas.deskripsiLomba',
                'lombas.tanggalBukaPendaftaran',
                'lombas.tanggalTutupPendaftaran',
                'lombas.posterLomba',
                'lombas.lampiran'
            )
            ->where('task.idLomba', $lomba->id)
            ->get();

        return view('app.mahasiswa.detailLomba', [
            'task' => $tasks,
            'lomba' => $lomba,
        ]);
    }

    public function register(Request $request, $idLomba)
    {
        try {
            // Menggunakan eager loading untuk mengambil semua data Mahasiswa beserta relasi Peserta
            $mahasiswas = Mahasiswa::with('pesertas')->get();

            foreach ($mahasiswas as $mahasiswa) {
                // Cek apakah mahasiswa sudah terdaftar untuk Lomba tertentu
                if (!$mahasiswa->pesertas->contains('idLomba', $idLomba)) {
                    // Buat entri baru di tabel Peserta
                    $peserta = new Peserta();
                    $peserta->idLomba = $idLomba;
                    $peserta->idMahasiswa = $mahasiswa->id;
                    $peserta->tanggalDaftar = now();
                    $peserta->save();

                    // Redirect ke halaman detail Lomba setelah berhasil mendaftar
                    return redirect()->route('app.mahasiswa.detailLomba', ['lomba' => $idLomba])->with('success', 'Anda telah berhasil mendaftar');
                }
            }

            // Jika semua mahasiswa sudah terdaftar, kirim pesan error
            return redirect()->back()->withErrors(['error' => 'Semua posisi telah terdaftar.']);
        } catch (\Exception $e) {
            // Handle exception if save fails
            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.']);
        }
    }

    public function PermissionTasks($idLomba)
    {
        // dd($idLomba);
        $datamhs = session('id');
        // dd($datamhs);
        $datamahasiswa = Mahasiswa::findOrFail($datamhs);
        $datamahasiswa->givePermissionTo('ViewTask');
        $datalomba = Lomba::findOrFail($idLomba);
        // Mengambil idMahasiswa dari data yang diambil
        // $idMahasiswa = $datamahasiswa->id;

        // Anda dapat mengganti ini dengan cara yang sesuai untuk mendapatkan idLomba
        $idLomba = $datalomba; // Misalnya, Anda dapat mengambilnya dari input form atau sesuai dengan logika aplikasi Anda

        // Menambahkan entri ke tabel peserta
        Peserta::create([
            'idLomba' => $idLomba,
            'idMahasiswa' => $datamahasiswa
        ]);

        return redirect()->route('mahasiswa.lomba')->with('success', 'Give permission as admin division to ' . $datamahasiswa->name);
    }
}
