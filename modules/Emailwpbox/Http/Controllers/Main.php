<?php

namespace Modules\Emailwpbox\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;

class Main extends Controller
{
    public function getTemplates()
    {
        //Get the company
        $company = $this->getCompany();

        //Get the templates from the company settings, make sure to return them as an array. Only return the ones that are not empty
        $templates = array_filter(array_map(function($i) use ($company) {
            $subject = $company->getConfig("EMAIL_TEMPLATE_{$i}_SUBJECT", '');
            $content = $company->getConfig("EMAIL_TEMPLATE_{$i}_BODY", '');
            if (empty($subject) || empty($content)) {
                return null;
            }
            return [
                'subject' => $subject,
                'content' => $content
            ];
        }, range(1, 5)));

        //For each template, get the first 4 words of content for the name
        $templates = array_map(function($template) {
            return [
                'content' => $template['content'],
                'subject' => $template['subject'],
                'name' => implode(' ', array_slice(explode(' ', str_replace(["\n", "\r"], ' ', $template['subject'])), 0, 4))
            ];
        }, $templates);

        return response()->json($templates);
    }

    public function sendEmail(Request $request)
    {
        //Get the company
        $company = $this->getCompany();

        //Get the email subject and message
        $subject = $request->input('subject');
        $message = $request->input('message');
        $email = $request->input('email');

        //Send the email
        return $this->sendEmailViaSMTP($company, $subject, $message, $email);
    }

    private function sendEmailViaSMTP($company, $subject, $markdownContent, $email)
    {
        //Send the email Via SMTP
       
        $smtpSettings = [
            'host' => $company->getConfig('MAIL_HOST', ''),
            'port' => (int) $company->getConfig('MAIL_PORT', '587'),
            'username' => $company->getConfig('MAIL_USERNAME', ''),
            'password' => $company->getConfig('MAIL_PASSWORD', ''),
            'encryption' => $company->getConfig('MAIL_ENCRYPTION', 'tls'),
            'from_address' => $company->getConfig('MAIL_FROM_ADDRESS', ''),
            'from_name' => $company->getConfig('MAIL_FROM_NAME', $company->name),
        ];



        

        // Set SMTP settings dynamically
        config([
            'mail.mailers.smtp.host' => $smtpSettings['host'],
            'mail.mailers.smtp.port' => $smtpSettings['port'],
            'mail.mailers.smtp.username' => $smtpSettings['username'],
            'mail.mailers.smtp.password' => $smtpSettings['password'],
            'mail.mailers.smtp.encryption' => $smtpSettings['encryption'],
            'mail.from.address' => $smtpSettings['from_address'],
            'mail.from.name' => $smtpSettings['from_name'],
        ]);

        // Send the email with dynamic subject and Markdown content
        try {
            $htmlContent = (string)$this->convertMarkdownToHtml($markdownContent);
            Mail::send([], [], function ($message) use ($subject, $htmlContent, $email) {
                $message->to($email)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'))
                        ->html($htmlContent);
            });
        } catch (\Exception $e) {
           // dd( $e->getMessage());
            //Return the error
            return response()->json(['success' => false,'error' => 'Email failed to send. Error: '.$e->getMessage()], 200);
        }

        return response()->json(['success' => true,'message' => 'Email sent successfully','smtpSettings' => $smtpSettings], 200);
    }

    // Convert Markdown to HTML
    protected function convertMarkdownToHtml($markdownContent)
    {
        $html = \Illuminate\Mail\Markdown::parse($markdownContent);
        //Wrap the html in a div with class "email-content", and make it look like a email
        return '<div class="email-content" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; box-sizing: border-box; background-color: #f8fafc; color: #74787e; height: 100%; line-height: 1.4; margin: 0; width: 100% !important; word-break: break-word; padding: 25px;">
            <div style="box-sizing: border-box; background-color: #ffffff; margin: 0 auto; padding: 25px; width: 570px; border-radius: 2px; box-shadow: 0 2px 0 rgba(0, 0, 150, 0.025), 2px 4px 0 rgba(0, 0, 150, 0.015);">
                ' . $html . '
            </div>
        </div>';
    }
}
