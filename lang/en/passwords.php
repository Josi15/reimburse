<?php

/*
 * Pesan alur reset password.
 *
 * Antarmuka FundBack berbahasa Indonesia, tetapi APP_LOCALE sengaja tetap "en"
 * agar seluruh pesan validasi bawaan Laravel tetap tersedia. Karena itu teks
 * Indonesia ditaruh di berkas locale "en" ini — Laravel akan memakainya
 * langsung tanpa perlu menerjemahkan ulang semua pesan validasi.
 */
return [
    'reset' => 'Password Anda sudah diperbarui. Silakan masuk dengan password baru.',
    // Sengaja berandai ("bila terdaftar"), bukan memastikan. Teks ini dipakai
    // untuk SEMUA hasil permintaan reset — termasuk email yang tidak terdaftar
    // — supaya halaman itu tak bisa dipakai menebak siapa saja penggunanya.
    // Kalimat yang memastikan pengiriman akan membocorkannya lewat teks.
    'sent' => 'Bila email tersebut terdaftar, tautan untuk mengatur ulang password sudah kami kirimkan. Cek juga folder spam.',
    'throttled' => 'Terlalu sering mencoba. Tunggu sebentar sebelum meminta tautan lagi.',
    'token' => 'Tautan reset password ini sudah tidak berlaku. Silakan minta tautan baru.',
    'user' => 'Bila email tersebut terdaftar, tautan reset sudah kami kirimkan.',
];
