<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\Console\Kernel;
use Pterodactyl\Notifications\MailTested;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Providers\SettingsServiceProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\MailSettingsFormRequest;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MailController extends Controller
{
    /**
     * MailController constructor.
     */
    public function __construct(
        private ConfigRepository $config,
        private Encrypter $encrypter,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * Render UI for editing mail settings.
     */
    public function index(): View
    {
        return view('admin.settings.mail', [
            'disabled' => $this->config->get('mail.default') !== 'smtp',
        ]);
    }

    /**
     * Handle request to update SMTP mail settings.
     * PROTECTED: Hanya ID 1 yang bisa mengubah konfigurasi email.
     */
    public function update(MailSettingsFormRequest $request): Response
    {
        // Gembok akses untuk selain ID 1
        if ($request->user()->id !== 1) {
            throw new AccessDeniedHttpException('Akses Ditolak: Konfigurasi SMTP hanya bisa diubah oleh Super Admin (ID 1).');
        }

        if ($this->config->get('mail.default') !== 'smtp') {
            throw new DisplayException('This feature is only available if SMTP is the selected email driver for the Panel.');
        }

        $values = $request->normalize();
        if (array_get($values, 'mail:mailers:smtp:password') === '!e') {
            $values['mail:mailers:smtp:password'] = '';
        }

        foreach ($values as $key => $value) {
            if (in_array($key, SettingsServiceProvider::getEncryptedKeys()) && !empty($value)) {
                $value = $this->encrypter->encrypt($value);
            }

            $this->settings->set('settings::' . $key, $value);
        }

        $this->kernel->call('queue:restart');

        return response('', 204);
    }

    /**
     * Submit a request to send a test mail message.
     * PROTECTED: Hanya ID 1 yang bisa melakukan test email.
     */
    public function test(Request $request): Response
    {
        if ($request->user()->id !== 1) {
            return response('Akses Ditolak.', 403);
        }

        try {
            Notification::route('mail', $request->user()->email)
                ->notify(new MailTested($request->user()));
        } catch (\Exception $exception) {
            return response($exception->getMessage(), 500);
        }

        return response('', 204);
    }
}
