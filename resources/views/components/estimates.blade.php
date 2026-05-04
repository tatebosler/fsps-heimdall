<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="flex flex-col">
    <div class="max-sm:mt-4 flex max-sm:flex-col items-center sm:gap-4 px-4 sm:px-8">
        <x-logo horizontal class="h-24" />
        <h1 class="ml-auto max-sm:mr-auto pb-2 sm:pt-2">Entry Time Estimates</h1>
    </div>
    <div class="sticky top-0 mb-4 mx-4">
        <div class="bg-yellow-800 text-yellow-100 px-3 py-2 border-yellow-700 border-2">
            <p class="sm:text-xl">All times on this page (except for actual admission times of groups that have already been admitted) are <strong>estimates and subject to change.</strong></p>
            <p class="mt-1 text-xs sm:text-base">If there is a conflict between this page and the signage and announcements at the Plant Sale, the signage and announcements take priority.</p>
        </div>
    </div>
    <div class="mx-4 sm:mx-8">
        <livewire:status-board switchable include-future-groups />
    </div>
</div>
