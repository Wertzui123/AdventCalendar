<?php

declare(strict_types=1);

namespace Wertzui123\AdventCalendar;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;

class EventListener implements Listener
{

    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onLogin(PlayerLoginEvent $ev)
    {
        $this->plugin->playerUIProfiles[strtolower($ev->getPlayer()->getName())] = $ev->getPlayer()->getPlayerInfo()->getExtraData()['UIProfile'];
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        if ($this->plugin->getConfig()->get('remind-on-join') === true && date('n') === '12' && !$this->plugin->hasTodayClaimed($event->getPlayer())) {
            $event->getPlayer()->sendMessage($this->plugin->getMessage('join_reminder'));
        }
    }

}