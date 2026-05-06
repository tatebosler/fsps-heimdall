<style>
h1 {
    margin: 0 0 4px;
}

h2 {
    margin: 12px 0 4px;
}

p {
    margin: 6px 0 0;
}

ul {
    margin: 4px 0 0;
    padding-left: 22px;
}

li {
    margin: 2px 0 0;
}

@media print {
    .gt-print-page-break {
        break-before: page;
        page-break-before: always;
    }

    .gt-print-ticket {
        break-inside: avoid;
        page-break-inside: avoid;
        margin-top: 0 !important;
    }

    .gt-print-ticket td,
    .gt-print-ticket tr,
    .gt-print-ticket img,
    .gt-print-ticket p {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<p>Hi {{ $first_name }}!</p>
<p>This email contains your Golden Ticket at the very bottom of this message (also attached as a PDF), which is needed to enter the volunteer pre-sale on <strong>{{ $presale_date }}</strong>. Please be prepared to show this QR code before you enter the pre-sale. You can either have it on your mobile device or printed on paper. If you print or screenshot, please make sure the six-digit serial number is also visible. Volunteers will be able to look up your record if you aren't able to or forget to bring your QR code with you.</p>
<p>
    <strong>New this year! We invested in some upgraded technology to expedite the entry process &mdash; this year should go much smoother than in the past. </strong>
</p>
@switch ($ticket->priorityDesignation())
@case ('shift_start')
    <h2>Group Zero Access for Thursday Volunteers</h2>
    <p>Because your shift takes place during the latter half of the pre-sale, your QR code has been coded to allow you to receive a <strong>Group Zero priority entrance wristband</strong>. This allows you to shop with the first entry group. Please note that this does not allow you to skip the wristband line &mdash; if you arrive while there is a line to get wristbands, you must go through that line, and you will receive your Group Zero wristband once you reach the front.</p>
    <p>Please plan to allow enough time to complete the plant check-out process and <strong>arrive on time for your shift</strong>. Any plants you purchase must be paid for and moved to your vehicle or taken off-site before you report to your volunteer assignment.</p>
    <p>Cashiers will not be available when your volunteer shift ends; we are not able to hold unpaid plants for checkout after your shift. To avoid confusion or mix-ups, do not leave plants in Plant Parking while you are volunteering: <strong>any plants left in Plant Parking at {{ $presale_close_time }} will be returned to inventory.</strong> Additionally, plants may not leave through the volunteer entrance/exit &mdash; they must exit out the west end towards curbside pickup.</p>
@break
@case ('shift_end')
    <h2>Group Zero Access for Thursday Volunteers</h2>
    <p>We recognize that volunteers working Thursday shifts that run up to the opening of the pre-sale do not have the same opportunity to wait in line for a wristband. In appreciation of your support during this critical time, your QR code has been coded for access to a Group Zero wristband, which will allow you to enter the pre-sale and begin shopping with the first entry group.</p>
    <p>When your shift has ended and you are ready to shop, you must first:</p>
    <ul>
        <li>Check out of your shift at the Volunteer Desk,</li>
        <li>Return your name tag, apron, vest, or other volunteer materials,</li>
        <li>Move your vehicle from the volunteer lot to customer parking in the Transit Hub</li>
    </ul>
    <p>From there, please go to the wristband booth. If a line is still in place, you must join it at that time. When your QR code is scanned, wristband staff will see your priority designation and issue you a Group Zero wristband.</p>

    <h2>Additional reminder</h2>
    <p>Shopping before checking out of your volunteer shift is not permitted. This includes selecting, setting aside, or "stashing" plants anywhere on site for later pickup. Any volunteer found shopping or holding plants before checking out may be subject to disciplinary action, including forfeiture of their Golden Ticket.</p>
@break
@case ('manual')
    <h2>Group Zero Access</h2>
    <p>Your Golden Ticket has been configured with Group Zero access. This does not allow you to skip the wristband line (if there is one when you arrive), but it will allow you to enter the presale with the first wristband group.</p>
@break
@endswitch
<h2>Pre-sale opening and wristband booth hours; other entry information</h2>
<p><strong>The pre-sale hours are {{ $presale_open_time }} to {{ $presale_close_time }}. Wristband distribution will open at {{ $wristband_distribution_start_time }}.</strong></p>
<p>You may bring a shopping helper along with you. Both individuals must be present at the time your Golden Ticket is scanned.</p>
<p>The pre-sale wristband is valid for {{ $presale_day }} only; same-day re-entry is permitted. If you are not given a wristband upon arrival and would like to re-enter the sale, you must get a wristband at the Info Desk before you check out.</p>
<p>If you are shopping after {{ $projected_off_bands_time }}, please go to the building entrance instead of the wristband booth. (Signage will direct you there once you arrive.) If you can't shop at all on Thursday, you can forward this email with the QR code to a friend or family member. As a reminder, Golden Tickets are valid for one scan only.</p>
<h2>Pre-sale parking and arrival information</h2>
<p><strong>Please use the customer parking lot in the Transit Hub for shopping on Thursday (1800 Randall Avenue, Falcon Heights, MN). You can get driving directions by scanning your QR code, or <a href="https://www.friendsschoolplantsale.com/driving">on our website</a>.</strong> Please do NOT park in the volunteer parking lot when you shop on {{ $presale_day }}. The volunteer lot is strictly reserved for individuals working an active shift and space is limited. You will find the customer lot is the optimal place for accessing the wristband booth, entrance to the sale, and utilizing curbside pickup.</p>
@if ($ticket->priorityDesignation() === 'shift_start')
<p>Once you have finished shopping, you are welcome (and encouraged!) to move your vehicle to the volunteer lot before your shift begins. The volunteer lot is the optimal place for accessing the volunteer check-in desk.</p>
@endif
<p>NOTE: Midway is no longer available for plant sale parking and many surrounding streets will have restricted parking &mdash; we ask that you pay close attention to any posted no-parking signs and road closures.</p>
<p>If you need accessible parking for shopping, please visit <a href="https://www.friendsschoolplantsale.com/accessibility">our dedicated accessibility page</a>. And if you plan on arriving at the pre-sale via any other means, please visit <a href="https://www.friendsschoolplantsale.com/doing-sale">our website</a> for more details.</p>

<h2>Conditions of Use</h2>
<p>Your Golden Ticket is directly linked to your volunteer record. If you cancel your volunteer shift, your Golden Ticket will be deactivated. Additionally, volunteers who no-show for their scheduled volunteer shift may forfeit Golden Ticket eligibility in future years.</p>
<p>The Plant Sale counts on over 2,000 volunteers and 10,000 volunteer hours every year to support Friends School of Minnesota. Thank you for being an important part of what makes the Plant Sale successful. We appreciate you and your support, and we look forward to welcoming you to the volunteer pre-sale on {{ $presale_date }}.</p>
<p>If you have any questions or if you need help with your Golden Ticket, please email <a href="mailto:signup@friendsschoolplantsale.com">signup@friendsschoolplantsale.com</a>.</p>
<p>On behalf of everyone on the Plant Sale committee, thank you for volunteering with us!</p>
<table role="presentation" align="center" border="0" cellpadding="0" cellspacing="0" class="gt-print-page-break gt-print-ticket" style="margin-left: auto; margin-right: auto; margin-top: 4px; break-inside: avoid-page; page-break-inside: avoid; border-collapse: collapse; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
    @if ($ticket->priority)
        <tr>
            <td align="center" style="padding: 0; border: 0; margin: 0;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 260px; border-collapse: collapse; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                    <tr>
                        <td style="color: #ffffff; background-color: #9333EA; padding: 4px; text-align: center; font-size: 20px; border: 0; margin: 0;">
                            <strong>GROUP ZERO</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif
    <tr>
        <td align="center" style="padding: 0; border: 0; margin: 0;">
            <img src="{{ $message->embedData($qrcode, 'qrcode.png', 'image/png') }}" style="width: 260px; height: 260px; display: block;">
        </td>
    </tr>
    <tr>
        <td align="center" style="padding: 0; border: 0; margin: 0;">
            <p style="font-size: 24px; font-weight: bold; margin: 0;">{{ $ticket->serial_number }}</p>
        </td>
    </tr>
</table>
