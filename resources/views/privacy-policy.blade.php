@php
    use App\Models\PlatformSetting;

    $email = PlatformSetting::get('support_email');
    $phone = PlatformSetting::get('support_phone');
    $address = PlatformSetting::get('company_address');
    $updated = '27 November 2026';
    $sections = [
        'about' => 'Who we are',
        'collect' => 'Information we collect',
        'use' => 'How we use it',
        'ai' => 'AI Coach',
        'sharing' => 'Who we share it with',
        'permissions' => 'App permissions',
        'security' => 'Security',
        'retention' => 'How long we keep it',
        'choices' => 'Your choices and rights',
        'delete-account' => 'Delete your account and data',
        'children' => 'Children',
        'changes' => 'Changes to this policy',
        'contact' => 'Contact and grievance officer',
    ];
@endphp
<x-layouts.base title="Privacy policy" body-class="theme-dark">
    <x-slot:head>
        <meta name="description" content="How the Fitnix app and website collect, use, share and protect your information, and how to delete your account and data.">
        <link rel="canonical" href="{{ route('privacy') }}">
    </x-slot:head>

    <div class="min-h-full px-4 py-10">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('home') }}" aria-label="Fitnix home"><x-brand.logo size="lg" /></a>

            <header class="mt-10">
                <h1 class="text-3xl font-bold text-white sm:text-4xl">Privacy policy</h1>
                <p class="mt-2 text-sm text-gym-muted">Last updated: {{ $updated }}</p>
                <p class="mt-5 leading-relaxed text-gym-light/90">
                    This policy explains what information the Fitnix member app, staff app and website (together, "Fitnix") collect, why,
                    who we share it with, and the choices you have, including how to delete your account and data.
                </p>
            </header>

            <nav aria-label="On this page" class="mt-8 rounded-2xl border border-gym-border bg-gym-charcoal p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gym-muted">On this page</h2>
                <ol class="mt-3 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                    @foreach ($sections as $id => $label)
                        <li><a href="#{{ $id }}" class="text-gym-light hover:text-brand-400">{{ $loop->iteration }}. {{ $label }}</a></li>
                    @endforeach
                </ol>
            </nav>

            <div class="mt-10 space-y-10 leading-relaxed text-gym-light/90 [&_h2]:scroll-mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-white [&_h3]:mt-5 [&_h3]:font-semibold [&_h3]:text-white [&_li]:mt-1.5 [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5 [&_a]:text-brand-400 [&_a]:underline">
                <section>
                    <h2 id="about">1. Who we are</h2>
                    <p>
                        Fitnix is gym management software. Gyms use it to manage their members, memberships, payments, attendance, trainers,
                        and diet and workout plans. Members use the Fitnix app to see their membership, check in, follow their plans and track their progress.
                    </p>
                    <p>
                        <strong class="text-white">Your gym</strong> decides which of your details to record and is responsible for them. Fitnix stores and
                        processes that information for your gym to provide the service. For the app itself, sign-in, notifications and the AI Coach, Fitnix is responsible.
                    </p>
                </section>

                <section>
                    <h2 id="collect">2. Information we collect</h2>
                    <h3>Account and profile</h3>
                    <ul>
                        <li>Name, mobile number, email address, date of birth, gender, blood group, address and city.</li>
                        <li>Emergency contact name, phone number and relationship.</li>
                        <li>Profile photo, if you or your gym add one.</li>
                        <li>Your gym, member code, trainer, joining date and membership details.</li>
                    </ul>
                    <h3>Health and fitness</h3>
                    <ul>
                        <li>Height, weight, weight goal and body measurements.</li>
                        <li>Fitness goal, diet preference and any medical notes your gym records.</li>
                        <li>Workout and diet plans assigned to you, and the days and meals you mark as done.</li>
                        <li>Water intake you log, and your reminder settings.</li>
                        <li>Progress photos, only if you upload them after agreeing to the progress photo consent.</li>
                    </ul>
                    <h3>Activity</h3>
                    <ul>
                        <li>Gym check-ins and check-outs (time and method, such as a QR scan), and your streaks.</li>
                        <li>Notifications we send you and whether you opened them.</li>
                        <li>Payments your gym records for your membership: amount, method, date and receipt. We do not collect card or bank details in the app.</li>
                    </ul>
                    <h3>Location</h3>
                    <ul>
                        <li>
                            If your number is not registered with any gym, you can choose to share your city or your location so our team can suggest
                            gyms near you. This happens only when you agree, at that moment. We do not track your location in the background.
                        </li>
                    </ul>
                    <h3>Device and technical</h3>
                    <ul>
                        <li>A push notification token, the platform (Android or iOS), app version and device name, so we can send you notifications.</li>
                        <li>IP address and sign-in records, used for security and to prevent abuse of sign-in codes.</li>
                    </ul>
                </section>

                <section>
                    <h2 id="use">3. How we use it</h2>
                    <ul>
                        <li>To sign you in with a one-time code sent by SMS, and to keep your account secure.</li>
                        <li>To show your membership, plans, attendance, payments and progress, and to let your gym manage them.</li>
                        <li>To send notifications you can control: gym announcements, membership and payment updates, and the meal, workout and water reminders you switch on.</li>
                        <li>To generate AI Coach reports, only with your consent (see below).</li>
                        <li>To provide support, fix problems and improve Fitnix.</li>
                    </ul>
                    <p>We do <strong class="text-white">not</strong> sell your information, and we do not use it for advertising.</p>
                </section>

                <section>
                    <h2 id="ai">4. AI Coach</h2>
                    <p>
                        The AI Coach is optional and works only after you agree to its consent. When you request a report, we send the relevant
                        measurements, weight history and, for photo comparisons, the selected progress photos to an AI model provider through
                        OpenRouter to produce the report. They are sent only to create your report, not for advertising.
                    </p>
                    <p>
                        You can withdraw your AI consent at any time in the app. Your AI reports and progress photos are then deleted.
                    </p>
                </section>

                <section>
                    <h2 id="sharing">5. Who we share it with</h2>
                    <ul>
                        <li><strong class="text-white">Your gym and its staff</strong> (owners, admins and trainers), who can see the details they need to manage your membership. A trainer may see only the members assigned to them.</li>
                        <li><strong class="text-white">SMS provider</strong> (SmartPing), to deliver your sign-in codes to your mobile number.</li>
                        <li><strong class="text-white">Google Firebase Cloud Messaging</strong>, to deliver push notifications to your phone.</li>
                        <li><strong class="text-white">OpenRouter and the AI model providers it connects to</strong>, only for AI Coach reports you request after giving consent.</li>
                        <li><strong class="text-white">Our hosting provider</strong>, which stores the data on our servers.</li>
                        <li><strong class="text-white">Authorities</strong>, when the law requires it, or to protect the rights and safety of users.</li>
                    </ul>
                    <p>These providers may use your information only to provide their service to us.</p>
                </section>

                <section>
                    <h2 id="permissions">6. App permissions</h2>
                    <ul>
                        <li><strong class="text-white">Camera</strong>: to scan your gym's check-in QR code and to take profile or progress photos. Nothing is recorded unless you choose to upload a photo.</li>
                        <li><strong class="text-white">Photos and media</strong>: to choose a profile or progress photo from your gallery.</li>
                        <li><strong class="text-white">Notifications</strong>: to show gym announcements and the reminders you switch on.</li>
                        <li><strong class="text-white">Location</strong> (optional): only to find gyms near you, as described above.</li>
                    </ul>
                    <p>You can turn any permission off in your phone's settings. The related feature then stops working.</p>
                </section>

                <section>
                    <h2 id="security">7. Security</h2>
                    <p>
                        Information is sent over encrypted connections (HTTPS). Each gym can see only its own members. Staff accounts are protected
                        with passwords or one-time codes, and sign-in codes expire after 10 minutes and are limited to a few attempts.
                        Progress photos are stored privately and shown only through short-lived links. No method of storage or transfer is completely
                        secure, but we work to protect your information.
                    </p>
                </section>

                <section>
                    <h2 id="retention">8. How long we keep it</h2>
                    <ul>
                        <li>Your account information is kept while you are a member of a gym on Fitnix, or until you ask us to delete it.</li>
                        <li>Progress photos and AI reports are kept until you delete them, withdraw consent, or delete your account.</li>
                        <li>Sign-in codes expire after 10 minutes. Security records are kept only as long as needed to prevent abuse.</li>
                        <li>Payment and invoice records may be kept longer where your gym or we must keep them for tax and accounting law.</li>
                    </ul>
                </section>

                <section>
                    <h2 id="choices">9. Your choices and rights</h2>
                    <ul>
                        <li>See and update your profile in the app (some details, such as your mobile number, are changed by your gym).</li>
                        <li>Turn notification types and reminders on or off in the app.</li>
                        <li>Withdraw your progress photo or AI Coach consent in the app. This deletes the related photos and reports.</li>
                        <li>Delete individual progress photos, or all your AI data, from the app's privacy settings.</li>
                        <li>Ask us for a copy of your information, to correct it, or to delete it, by contacting us below.</li>
                    </ul>
                </section>

                <section>
                    <h2 id="delete-account">10. Delete your account and data</h2>
                    <p>You can ask us to delete your Fitnix account and the data linked to it at any time:</p>
                    <ul>
                        <li>
                            Email
                            @if ($email)<a href="mailto:{{ $email }}?subject={{ rawurlencode('Delete my Fitnix account') }}">{{ $email }}</a>@else our support team @endif
                            from any address, with the subject "Delete my Fitnix account". Include your name, the mobile number registered with your gym, and your gym's name.
                        </li>
                        <li>Or ask your gym's front desk, who will send the request to us.</li>
                    </ul>
                    <p>
                        To protect you, we may confirm the request with a one-time code sent to your registered number. We complete deletion within
                        <strong class="text-white">30 days</strong> and tell you when it is done.
                    </p>
                    <h3>What is deleted</h3>
                    <ul>
                        <li>Your profile and contact details, profile photo, emergency contact and medical notes.</li>
                        <li>Progress photos, measurements, weight logs, AI Coach reports, water logs, reminder settings and workout and diet logs.</li>
                        <li>Your registered devices, notifications and sign-in sessions.</li>
                    </ul>
                    <h3>What may be kept</h3>
                    <ul>
                        <li>
                            Payment, receipt and invoice records your gym needs to keep under tax and accounting law (generally up to 8 years in India).
                            These keep only what the law requires and are not used for anything else.
                        </li>
                        <li>Records of the consents you gave or withdrew, kept as proof of your choice.</li>
                    </ul>
                    <p>To delete only your progress photos and AI data, without deleting your account, use the privacy settings in the app.</p>
                </section>

                <section>
                    <h2 id="children">11. Children</h2>
                    <p>
                        Fitnix is not meant for children under 13. Members under 18 can use Fitnix only when their gym registers them with the
                        consent of a parent or guardian. If you believe a child's information was added without that consent, contact us and we will delete it.
                    </p>
                </section>

                <section>
                    <h2 id="changes">12. Changes to this policy</h2>
                    <p>
                        We may update this policy as Fitnix changes. We will change the "Last updated" date above and, for important changes,
                        tell you in the app. Where a change affects something you agreed to (such as the AI Coach or progress photos), we will ask you again.
                    </p>
                </section>

                <section>
                    <h2 id="contact">13. Contact and grievance officer</h2>
                    <p>For questions, requests or complaints about your information, contact our grievance officer:</p>
                    <ul>
                        @if ($email)<li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>@endif
                        @if ($phone)<li>Phone: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></li>@endif
                        @if ($address)<li>Address: {{ $address }}</li>@endif
                    </ul>
                    <p>We reply within 30 days.</p>
                </section>
            </div>

            <footer class="mt-14 border-t border-gym-border pt-6 text-sm text-gym-muted">
                <p>© {{ now()->year }} Fitnix. <a href="{{ route('home') }}" class="text-gym-light hover:text-brand-400">Back to home</a></p>
            </footer>
        </div>
    </div>
</x-layouts.base>
