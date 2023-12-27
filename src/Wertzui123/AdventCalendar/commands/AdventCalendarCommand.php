<?php

declare(strict_types=1);

namespace Wertzui123\AdventCalendar\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use Wertzui123\AdventCalendar\Main;

class AdventCalendarCommand extends Command implements PluginOwned
{
    use PluginOwnedTrait;

    private $plugin;

    public function __construct(Main $plugin)
    {
        parent::__construct($plugin->getConfig()->getNested('command.command'), $plugin->getConfig()->getNested('command.description'), $plugin->getConfig()->getNested('command.usage'), $plugin->getConfig()->getNested('command.aliases'));
        $this->setPermissions(['adventcalendar.command.adventcalendar']);
        $this->setPermissionMessage($plugin->getMessage('command.adventcalendar.noPermission'));
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!($sender instanceof Player)) {
            $sender->sendMessage($this->plugin->getMessage('command.adventcalendar.runIngame'));
            return;
        }
        if (date('n') !== '12') {
            $sender->sendMessage($this->plugin->getMessage('command.adventcalendar.notDecember'));
            return;
        }
        $this->plugin->openAdventCalendarInventory($sender);
    }

}