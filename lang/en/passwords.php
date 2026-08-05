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
    'sent' => 'Tautan untuk mengatur ulang password sudah dikirim ke email Anda. Cek juga folder spam.',
    'throttled' => 'Terlalu sering mencoba. Tunggu sebentar sebelum meminta tautan lagi.',
    'token' => 'Tautan reset password ini sudah tidak berlaku. Silakan minta tautan baru.',
    // Sengaja netral: jawaban yang sama untuk email terdaftar maupun tidak,
    // supaya halaman ini tak bisa dipakai menebak siapa saja penggunanya.
    'user' => 'Bila email tersebut terdaftar, tautan reset sudah kami kirimkan.',
];
