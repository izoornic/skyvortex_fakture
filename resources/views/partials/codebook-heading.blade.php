<div>
    <flux:heading size="xl">Šifarnici</flux:heading>
    <flux:subheading>Zajednički za sva pravna lica; uređuje ih administrator</flux:subheading>

    <flux:navbar class="mt-4 -mb-px max-w-full overflow-x-auto">
        <flux:navbar.item :href="route('codebooks.currencies')" :current="request()->routeIs('codebooks.currencies')" wire:navigate>
            Valute
        </flux:navbar.item>
        <flux:navbar.item :href="route('codebooks.units')" :current="request()->routeIs('codebooks.units')" wire:navigate>
            Jedinice mere
        </flux:navbar.item>
        <flux:navbar.item :href="route('codebooks.vat-rates')" :current="request()->routeIs('codebooks.vat-rates')" wire:navigate>
            PDV stope
        </flux:navbar.item>
        <flux:navbar.item :href="route('codebooks.vat-exemptions')" :current="request()->routeIs('codebooks.vat-exemptions')" wire:navigate>
            Osnovi oslobođenja
        </flux:navbar.item>
    </flux:navbar>

    <flux:separator />
</div>
