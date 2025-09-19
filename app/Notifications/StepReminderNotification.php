<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StepReminderNotification extends Notification
{
    use Queueable;


      protected $step;
    protected $channel;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
     public function __construct($step, $channel = 'mail')
    {
        $this->step = $step;
        $this->channel = $channel;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
         // Retourne le canal choisi
        return [$this->channel];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
     public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Relance pour étape en retard')
                    ->line("Vous devez valider l'étape : {$this->step->workflow_step->name}")
                    ->action('Voir le workflow', url("/workflow/instance/{$this->step->workflow_instance_id}"));
    }

        public function toDatabase($notifiable)
    {
        return [
            'instance_step_id' => $this->step->id,
            'message' => "Vous devez valider l'étape : {$this->step->workflow_step->name}"
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
