<?php

test('a meter fills in proportion to its value', function () {
    $this->blade('<x-meter :value="25" :max="100" label="Used" />')
        ->assertSee('role="progressbar"', false)
        ->assertSee('aria-valuenow="25"', false)
        ->assertSee('aria-label="Used"', false)
        ->assertSee('width: 25%', false);
});

test('a meter clamps an overflowing value and never divides by zero', function () {
    $this->blade('<x-meter :value="150" :max="100" />')
        ->assertSee('width: 100%', false)
        ->assertSee('aria-valuenow="100"', false);

    $this->blade('<x-meter :value="5" :max="0" />')
        ->assertSee('width: 0%', false);

    $this->blade('<x-meter :value="-4" :max="10" />')
        ->assertSee('width: 0%', false);
});

test('a list row links only when given an href', function () {
    $this->blade('<ul><x-list-row href="/payroll">Run <x-slot name="meta">3 employees</x-slot><x-slot name="trailing">Done</x-slot></x-list-row></ul>')
        ->assertSee('href="/payroll"', false)
        ->assertSeeText('Run')
        ->assertSeeText('3 employees')
        ->assertSeeText('Done');

    $this->blade('<ul><x-list-row>Plain</x-list-row></ul>')
        ->assertDontSee('href=', false)
        ->assertSeeText('Plain');
});

test('a stat card shows its progress meter, trend and alert accent only when asked', function () {
    $this->blade('<x-stat-card label="Active" :value="8" :progress="80" trend="80%" trendTone="up" :alert="true" />')
        ->assertSee('role="progressbar"', false)
        ->assertSee('width: 80%', false)
        ->assertSeeText('80%')
        ->assertSee('border-l-yellow-500', false);

    $this->blade('<x-stat-card label="Active" :value="8" />')
        ->assertDontSee('role="progressbar"', false)
        ->assertDontSee('border-l-yellow-500', false);
});

test('a flush card drops its body padding', function () {
    $this->blade('<x-card title="List" :flush="true">Body</x-card>')
        ->assertDontSee('p-4 sm:p-6"', false);
});
