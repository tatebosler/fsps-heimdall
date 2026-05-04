<x-layouts.app>
    <div class="grid sm:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-8 m-4 sm:m-8">
        <div class="sm:hidden">
            <x-logo horizontal />
        </div>
        <div class="max-sm:hidden lg:hidden">
            <x-logo />
        </div>
        <div class="max-lg:hidden col-span-3">
            <x-logo horizontal />
        </div>
        <div class="sm:col-span-2">
            <livewire:entry-status />
        </div>
    </div>
    <div class="bg-gray-300 dark:bg-gray-700 p-4 sm:p-8 flex flex-col lg:flex-row items-center gap-4 sm:gap-8">
        <div class="md:text-center lg:text-left">
            <h1 class="font-bold text-2xl sm:text-4xl">Welcome to the Plant Sale</h1>
            <p class="mt-1 sm:mt-2 text-lg">Use these tools to help make your entry and shopping experience seamless and enjoyable.</p>
        </div>
        <div class="grid lg:ml-auto gap-1 sm:gap-2 sm:grid-cols-2 lg:grid-cols-1 lg:w-2/5">
            <a href="/notifications" class="dark:bg-{{ config('ps.colors.' . date('l')) }}-800 hover:dark:bg-{{ config('ps.colors.' . date('l')) }}-700 active:dark:bg-{{ config('ps.colors.' . date('l')) }}-600 bg-{{ config('ps.colors.' . date('l')) }}-300 hover:bg-{{ config('ps.colors.' . date('l')) }}-200 active:bg-{{ config('ps.colors.' . date('l')) }}-100 px-3 py-2 rounded-xl w-full block">
                <p class="text-xl font-bold">Sign up for text messages</p>
                <p class="text-sm">and get notified when it's your turn to shop, or when there's no wait to get in for the rest of the day.</p>
            </a>
            <a href="/estimates" class="dark:bg-{{ config('ps.colors.' . date('l')) }}-800 hover:dark:bg-{{ config('ps.colors.' . date('l')) }}-700 active:dark:bg-{{ config('ps.colors.' . date('l')) }}-600 bg-{{ config('ps.colors.' . date('l')) }}-300 hover:bg-{{ config('ps.colors.' . date('l')) }}-200 active:bg-{{ config('ps.colors.' . date('l')) }}-100 px-3 py-2 rounded-xl w-full flex items-center">
                <p class="text-xl font-bold">Check estimated entry times</p>
            </a>
            <a href="/notifications" class="dark:bg-gray-800 hover:dark:bg-gray-900 active:dark:bg-gray-950 bg-gray-200 hover:bg-gray-100 active:bg-gray-50 px-3 py-2 rounded-xl w-full block">
                <p>Update/cancel your notifications</p>
            </a>
            <a href="https://www.friendsschoolplantsale.com/doing-sale" class="dark:bg-gray-800 hover:dark:bg-gray-900 active:dark:bg-gray-950 bg-gray-200 hover:bg-gray-100 active:bg-gray-50 px-3 py-2 rounded-xl w-full block">
                <p>Arrival and parking information</p>
            </a>
        </div>
    </div>
    <div class="grid sm:grid-cols-2 p-4 sm:p-8 gap-4 sm:gap-8">
        <div class="space-y-1 sm:space-y-2">
            <h2 class="text-xl sm:text-2xl font-semibold">About the Plant Sale</h2>
            <p>The Friends School Plant Sale is one of the largest of its kind in the U.S. Every year on Mothers Day weekend, 20,000 people and over 250,000 plants descend on the Minnesota State Fairgrounds to raise money for Friends School of Minnesota, a Quaker K-8 school in Saint Paul.</p>
            <p>The sale has happened every year since 1990 (with a significantly reduced footprint in 2020, and format changes in 2021). The sale raises over $500,000 each year, all of which helps support a strong financial aid program and keeps tuition affordable for all families.</p>
            <p>The sale is almost entirely run by volunteers, plus a dedicated organizing committee that meets year-round to plan the sale.</p>
        </div>
        <div class="space-y-1 sm:space-y-2">
            <h2 class="text-xl sm:text-2xl font-semibold">About our entry process</h2>
            <p>Our plant sale is incredibly popular &mdash; our earliest customer arrival is 12:30 AM! Since 2008, our wristband system has allowed customers to hold their place in line without needing to physically stand in line for multiple hours.</p>
            <p>Here's how it works:</p>
            <ol class="list-decimal ml-8 space-y-0.5 sm:space-y-1">
                <li>When you arrive at the Plant Sale, proceed directly to the Wristband Booth in the Garden Fair. If you arrive before the Wristband Booth opens, join the back of the line. (If you park in an accessible parking lot, there are dedicated wristband distribution points in each lot.)</li>
                <li>Each person in your group will receive a numbered wristband. Once you get yours, you can <a href="/notifications" class="text-emerald-500 hover:text-emerald-600 active:text-emerald-700 dark:text-emerald-300 hover:dark:text-emerald-200 active:dark:text-emerald-100">sign up for text message notifications</a> (messaging and data rates may apply) or <a href="/estimates" class="text-emerald-500 hover:text-emerald-600 active:text-emerald-700 dark:text-emerald-300 hover:dark:text-emerald-200 active:dark:text-emerald-100">track your group's estimated entry time</a>.</li>
                <li>You can <a href="https://www.friendsschoolplantsale.com/gardenfair" class="text-emerald-500 hover:text-emerald-600 active:text-emerald-700 dark:text-emerald-300 hover:dark:text-emerald-200 active:dark:text-emerald-100">explore the Garden Fair</a>, including our food vendors, while you wait for your group to be called. Signage at the Information tent and Wristband Booth is periodically updated with which groups are welcome to enter the sale. You can also choose to leave the area entirely and come back later.</li>
                <li>When your group is called, follow the signage down the hill, past Sweet Martha's, and into the Grandstand. Show your wristband to the volunteers at the entrance gate and enjoy the sale!</li>
            </ol>
            <div class="bg-blue-200 text-blue-950 dark:bg-blue-800 dark:text-blue-50 rounded-xl border-blue-400 dark:border-blue-600 border-2 p-4 flex items-start gap-3">
                <div class="fab fa-accessible-icon text-xl mt-1.5"></div>
                <div>
                    <p class="text-lg sm:text-xl mb-2"><strong>We've improved our accessibillity offerings and accessible entry process for 2026</strong></p>
                    <p>Customers with disability parking certificates or plates should park in our new accessible parking lot to enter the Plant Sale. Don't worry... we'll have volunteers on hand to distribute wristbands and answer questions once you arrive. You can also park in the accessible section of the general parking lot, if that's your preference. <a href="https://www.friendsschoolplantsale.com/accessibility" class="text-blue-800 hover:text-blue-900 active:text-blue-950 dark:text-blue-200 hover:dark:text-blue-100 active:dark:text-blue-50 font-bold">Learn more &rarr;</a></p>
                </div>
            </div>
            <p>Some policy notes to help you get and use your wristband smoothly:</p>
            <ul class="list-disc ml-8 space-y-0.5 sm:space-y-1">
                <li>Wristbands are required for everyone age 13 and up. Kids age 12 and under do not need their own wristbands and must be accompanied by an adult.</li>
                <li>Wristbands are valid only for the day they are issued. If you want to shop on multiple days, you'll need to get a wristband each day.</li>
                <li>We cannot give you extra wristbands for members of your group who are parking, arriving later, or otherwise not present. Everyone will get their own wristband when they individually arrive.</li>
                <li>You may enter the Plant Sale at any time between when your group is called and when the sale closes for the day.</li>
                <li>You may re-enter the Plant Sale the same day without getting a new wristband, so long as you keep your wristband on and intact.</li>
                <li>Wristbands are void if removed or tampered with.</li>
            </ul>
            <div class="bg-yellow-200 text-yellow-950 dark:bg-yellow-800 dark:text-yellow-50 rounded-xl border-yellow-400 dark:border-yellow-600 border-2 p-4 flex items-start gap-3">
                <div class="fas fa-exclamation-triangle text-xl mt-1.5"></div>
                <div>
                    <p class="text-lg sm:text-xl mb-2"><strong>Heads up to volunteers shopping on Thursday</strong></p>
                    <p>Your Golden Ticket is only valid for one scan, and will be scanned either at the entrance gate or at the Wristband Booth. This means that if you do not receive a wristband when your ticket is scanned, and you wish to re-enter the sale later that day, <strong>you must get a wristband at the Info Desk inside the sale before you check out.</strong></p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
