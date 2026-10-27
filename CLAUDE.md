<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Aturan Project

- Selalu berpedoman pada rules.md.
- Kerjakan per fase sesuai section 85 rules.md, verifikasi tiap fase sebelum lanjut.
- Semua query database WAJIB memakai Eloquent ORM
  (jangan pakai DB::table(), DB::select(), atau raw SQL).
- Balas dalam Bahasa Indonesia.
- Project memakai Livewire 4 (bukan Livewire 3 seperti tertulis di rules.md), sudah disetujui.
- Bahasa aplikasi: Indonesia (`APP_LOCALE=id`). Tulis teks UI dalam bahasa Inggris di dalam `__()` / `trans_choice()`, lalu tambahkan terjemahannya ke `lang/id.json`. `LocalizationTest` akan gagal jika ada teks tanpa terjemahan.

# Don'ts

- Don't expose IDs directly on the front end
  Don't include any credentials or environment-specific information on the front end.

# Dos

- Use the MVC pattern
- Write clean code
  You can check out some of my projects in the same project directory and adopt my coding style

---
