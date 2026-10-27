{{--
    Illustrated phone for the marketing page when no photo is uploaded. The screen is the slot;
    botanical line art frames it (x-motif.sulur).
--}}
<div {{ $attributes->class('relative') }} aria-hidden="true">
    <div class="relative overflow-hidden rounded-[2.4rem] border-[9px] border-[#2b2b28] bg-[#f6f2ea] shadow-[0_40px_70px_-30px_rgb(31_45_42/0.55)] ring-1 ring-black/10">
        <div class="absolute left-1/2 top-2 z-10 h-5 w-24 -translate-x-1/2 rounded-full bg-[#2b2b28]"></div>
        <x-motif.sulur class="pointer-events-none absolute -right-6 -top-4 w-32 -scale-x-100 rotate-180 text-olive/40" accent="#c9a59a" />
        <x-motif.sulur class="pointer-events-none absolute -bottom-4 -left-6 w-32 text-olive/40" accent="#c9a59a" />
        <div class="relative aspect-[9/18.5]">
            {{ $slot }}
        </div>
    </div>
</div>
