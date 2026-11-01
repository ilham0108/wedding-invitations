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

## Anti-slop (antislop)

- Urutan prioritas jika bertentangan:
  - UI (tampilan, layout, CSS, komponen Blade, copywriting, aksesibilitas): instruksi user di sesi > antislop > CLAUDE.md > rules.md > DESIGN.md.
  - Selain UI (arsitektur, database, backend, alur fitur, fase pengerjaan): instruksi user di sesi > CLAUDE.md > rules.md > antislop.
- Jika aturan antislop mengalahkan rules.md, sebutkan konfliknya dalam satu baris.
- Arah desain (R-37) diambil dari `DESIGN.md` dan rules.md section 4, selama tidak melanggar antislop.
- Hard Gate antislop berlaku di semua UI, termasuk template undangan. Ciri khas template (`resources/views/designs/*`, `resources/css/designs/*`) seperti efek kaca mocha-glass, ornamen dan ilustrasi botanical-cream, animasi pembuka, dan tema tetap per template diperlakukan sebagai Purpose-Gate: boleh dipakai dengan alasan tertulis di `DESIGN.md` (R-31), bukan dikecualikan otomatis.
- Aturan copywriting antislop (R-02, R-15, R-16) diterapkan pada teks sumber bahasa Inggris di `__()` dan terjemahannya di `lang/id.json`.
- Konten seeder/demo data (rules.md section 72) boleh berisi nama dan data contoh. R-17/R-18 berlaku untuk konten yang tampil di halaman publik/marketing: jangan mengarang testimoni, statistik, atau klaim.

# Don'ts

- Don't expose IDs directly on the front end
  Don't include any credentials or environment-specific information on the front end.

# Dos

- Use the MVC pattern
- Write clean code
  You can check out some of my projects in the same project directory and adopt my coding style

---

<!-- antislop:start -->
## antislop
For UI, copy, people, mobile layout, or code comments work, read these installed skill files directly (use these paths even if a same-named global skill exists):
- Core filter, always on: `antislop`: `.claude/skills/antislop/SKILL.md`
- UI / visual: `antislop-ui`: `.claude/skills/antislop-ui/SKILL.md`
- Copy & text: `antislop-copywriting`: `.claude/skills/antislop-copywriting/SKILL.md`
- People: `antislop-human`: `.claude/skills/antislop-human/SKILL.md`
- Mobile / responsive: `antislop-layoutmobile`: `.claude/skills/antislop-layoutmobile/SKILL.md`
- Code comments: `antislop-code`: `.claude/skills/antislop-code/SKILL.md`
Before starting, follow the core's "Two Usage Modes" section in strict order: explicit session instruction first, then global preference, then ask. A session instruction always wins. For a resolved mode, say `antislop active: <mode> (session override).` or `antislop active: <mode> (global preference).` once before presenting findings or making edits, using the actual mode and source. Acknowledging the user's request without naming the source does not replace this notice.
Only an explicit choice of antislop during or after selects a session mode. A request to review, audit, or avoid file edits does not select a mode; read the global preference in that case. Another skill's mode does not select antislop's mode.
If the mode is unresolved, ask during/after and end the response; wait for the answer before any UI review, planning, or concept. For read-only tasks, put the active-mode notice only at the start of the final answer, never in progress messages. For editing tasks, announce before the first edit and omit it from the final answer.
To update antislop later: `npx antislop-ai --update`, or run `npx antislop-ai` and pick Overwrite them.
<!-- antislop:end -->
