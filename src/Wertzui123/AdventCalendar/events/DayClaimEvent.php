<?php

declare(strict_types=1);

namespace Wertzui123\AdventCalendar\events;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

class DayClaimEvent extends PlayerEvent implements Cancellable
{
    use CancellableTrait;

    /** @var string[] */
    private $commands;

    /**
     * DayClaimEvent constructor.
     * @param Player $player
     * @param string[] $commands
     */
    public function __construct(Player $player, array $commands)
    {
        $this->player = $player;
        $this->commands = $commands;
    }

    /**
     * Returns the commands to execute
     * @return string[]
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Updates the commands to execute
     * @param string[] $commands
     */
    public function setCommands(array $commands)
    {
        $this->commands = $commands;
    }

}