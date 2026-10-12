{{-- Fields of a loan or other deduction; $deduction is null when adding one. --}}
<div class="grid gap-4 sm:grid-cols-4">
    <div class="sm:col-span-2">
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" class="mt-1" placeholder="GSIS Consolidated Loan" :value="old('name', $deduction?->name)" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="reference" :value="__('Loan / account no. (optional)')" />
        <x-text-input id="reference" name="reference" class="mt-1" :value="old('reference', $deduction?->reference)" />
        <x-input-error :messages="$errors->get('reference')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="priority" :value="__('Priority (1 = first)')" />
        <x-text-input id="priority" name="priority" type="number" min="1" max="999" class="mt-1" :value="old('priority', $deduction?->priority ?? 100)" />
        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="monthly_amount" :value="__('Monthly amount (₱)')" />
        <x-text-input id="monthly_amount" name="monthly_amount" inputmode="decimal" class="mt-1" :value="old('monthly_amount', $deduction?->monthly_amount)" required />
        <x-input-error :messages="$errors->get('monthly_amount')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="total_amount" :value="__('Total to pay (optional, ₱)')" />
        <x-text-input id="total_amount" name="total_amount" inputmode="decimal" class="mt-1" :value="old('total_amount', $deduction?->total_amount)" />
        <x-input-error :messages="$errors->get('total_amount')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="starts_on" :value="__('Starts')" />
        <x-text-input id="starts_on" name="starts_on" type="date" class="mt-1" :value="old('starts_on', $deduction?->starts_on?->toDateString() ?? now()->startOfMonth()->toDateString())" required />
        <x-input-error :messages="$errors->get('starts_on')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="ends_on" :value="__('Ends (optional)')" />
        <x-text-input id="ends_on" name="ends_on" type="date" class="mt-1" :value="old('ends_on', $deduction?->ends_on?->toDateString())" />
        <x-input-error :messages="$errors->get('ends_on')" class="mt-2" />
    </div>
    <div class="sm:col-span-4">
        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
        <x-text-input id="remarks" name="remarks" class="mt-1" :value="old('remarks', $deduction?->remarks)" />
        <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
    </div>
</div>
