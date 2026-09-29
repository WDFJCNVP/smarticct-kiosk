<?php

use Livewire\Component;

new class extends Component
{
    // The "How would you like to sign in?" step is redundant now: the landing page already
    // listens for a card tap and has a "Use my account" button. The route is kept so nothing
    // that still points here breaks; it simply lands on the landing page.
    public function mount()
    {
        $this->redirect(route('kiosk.home'), navigate: true);
    }
};
?>

<div></div>