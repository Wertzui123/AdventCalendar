<?php

declare(strict_types=1);

namespace Wertzui123\AdventCalendar;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\inventory\Inventory;
use pocketmine\item\ItemTypeIds;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\sound\AnvilFallSound;
use pocketmine\world\sound\NoteInstrument;
use pocketmine\world\sound\NoteSound;
use pocketmine\world\sound\XpLevelUpSound;
use Wertzui123\AdventCalendar\commands\AdventCalendarCommand;
use Wertzui123\AdventCalendar\events\DayClaimEvent;

class Main extends PluginBase
{

    const CONFIG_VERSION = '1.1';

    private string $prefix;
    private Config $stringsFile;
    private Config $claimedFile;

    /** @internal */
    public array $playerUIProfiles = [];

    public function onEnable(): void
    {
        $this->configUpdater();
        $this->saveResource('strings.yml');
        $this->stringsFile = new Config($this->getDataFolder() . 'strings.yml', Config::YAML);
        $this->claimedFile = new Config($this->getDataFolder() . 'claimed.json', Config::JSON);
        $this->prefix = $this->getString('prefix');
        if (!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getServer()->getCommandMap()->register('AdventCalendar', new AdventCalendarCommand($this));
    }

    /**
     * @internal
     * @return Config
     */
    public function getStringsFile(): Config
    {
        return $this->stringsFile;
    }

    /**
     * @internal
     * @return Config
     */
    public function getClaimedFile(): Config
    {
        return $this->claimedFile;
    }

    /**
     * @internal
     * Returns a string from the strings file
     * @param string $key
     * @param array $replace [optional]
     * @param mixed $default [optional]
     * @return string|mixed
     */
    public function getString(string $key, array $replace = [], $default = '')
    {
        return str_replace(array_keys($replace), $replace, $this->getStringsFile()->getNested($key, $default));
    }

    /**
     * @internal
     * Returns a message from the strings file
     * @param string $key
     * @param array $replace [optional]
     * @param mixed $default [optional]
     * @return string|mixed
     */
    public function getMessage(string $key, array $replace = [], $default = '')
    {
        return $this->prefix . $this->getString($key, $replace, $default);
    }

    /**
     * Opens the advent calendar inventory for a player
     * @param Player $player
     */
    public function openAdventCalendarInventory(Player $player)
    {
        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $menu->setName($this->getString('inventory.title'));
        $menu->setListener(function (InvMenuTransaction $transaction): InvMenuTransactionResult {
            if ($transaction->getItemClicked()->getTypeId() === ItemTypeIds::fromBlockTypeId(BlockTypeIds::WOOL) && $transaction->getItemClicked()->getBlock()->getColor() === DyeColor::GREEN()) {
                if (!$this->hasTodayClaimed($transaction->getPlayer())) {
                    $event = new DayClaimEvent($transaction->getPlayer(), $this->getConfig()->getNested('rewards.' . date('d'), $this->getConfig()->getNested('rewards.default')));
                    $event->call();
                    if ($event->isCancelled()) {
                        NetworkBroadcastUtils::broadcastPackets([$transaction->getPlayer()], (new AnvilFallSound())->encode($transaction->getPlayer()->getPosition()));
                        return $transaction->discard();
                    }
                    foreach ($event->getCommands() as $command) {
                        $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), str_replace('{player}', '"' . $transaction->getPlayer()->getName() . '"', $command));
                    }
                    NetworkBroadcastUtils::broadcastPackets([$transaction->getPlayer()], (new XpLevelUpSound(100))->encode($transaction->getPlayer()->getPosition()));
                    $this->setTodayClaimed($transaction->getPlayer());
                    $this->fillInventory($transaction->getAction()->getInventory(), $transaction->getPlayer());
                } else {
                    NetworkBroadcastUtils::broadcastPackets([$transaction->getPlayer()], (new NoteSound(NoteInstrument::PIANO(), 0))->encode($transaction->getPlayer()->getPosition()));
                }
            } else {
                NetworkBroadcastUtils::broadcastPackets([$transaction->getPlayer()], (new NoteSound(NoteInstrument::PIANO(), 0))->encode($transaction->getPlayer()->getPosition()));
            }
            return $transaction->discard();
        });
        $this->fillInventory($menu->getInventory(), $player);
        $menu->send($player);
    }

    /**
     * @internal
     * Fills the given inventory with the advent calendar items
     * @param Inventory $inventory
     * @param Player $player
     */
    private function fillInventory(Inventory $inventory, Player $player)
    {
        $dayIndex = 0;
        $currentDay = intval(date('d'));
        for ($i = 0; $i < $inventory->getSize(); $i++) {
            if (!isset($this->playerUIProfiles[strtolower($player->getName())])) $this->playerUIProfiles[strtolower($player->getName())] = 0;
            $condition = match ($this->playerUIProfiles[strtolower($player->getName())]) {
                0 => $i > 8 && $i < 44 && $i % 9 > 0 && $i % 9 < 8, // classic
                default => $i > 5 && $i < 42 && $i % 6 > 0 && $i % 6 < 5 // pocket
            };
            if ($condition) {
                if ($dayIndex < 24) {
                    $color = DyeColor::RED();
                    if ($currentDay > ($dayIndex + 1)) {
                        if ($this->hasDayClaimed($player, $dayIndex + 1)) {
                            $color = DyeColor::BLUE();
                        } else {
                            $color = DyeColor::GRAY();
                        }
                    } elseif ($currentDay === ($dayIndex + 1)) {
                        if ($this->hasDayClaimed($player, $currentDay)) {
                            $color = DyeColor::LIGHT_BLUE();
                        } else {
                            $color = DyeColor::GREEN();
                        }
                    }
                    $inventory->setItem($i, VanillaBlocks::WOOL()->setColor($color)->asItem()->setCustomName($this->getString('inventory.day', ['{day}' => ($dayIndex + 1)])));
                } else {
                    $inventory->setItem($i, VanillaBlocks::INVISIBLE_BEDROCK()->asItem()->setCustomName(' '));
                }
                $dayIndex++;
            } else {
                $inventory->setItem($i, VanillaBlocks::INVISIBLE_BEDROCK()->asItem()->setCustomName(' '));
            }
        }
    }

    /**
     * @internal
     * Checks whether the given player has claimed their rewards for the given day
     * @param Player $player
     * @param int $day
     * @return bool
     */
    private function hasDayClaimed(Player $player, int $day)
    {
        $year = $this->getClaimedFile()->get(date('Y'), []);
        $strDay = (string)$day;
        if ($day < 10) $strDay = '0' . $strDay;
        $day = $year[$strDay] ?? [];
        return in_array(strtolower($player->getName()), $day);
    }

    /**
     * Checks whether the given player has claimed their rewards today
     * @param Player $player
     * @return bool
     */
    public function hasTodayClaimed(Player $player)
    {
        return $this->hasDayClaimed($player, intval(date('d')));
    }

    /**
     * @internal
     * Marks the given player as having claimed their rewards today
     * @param Player $player
     * @throws \JsonException
     */
    private function setTodayClaimed(Player $player)
    {
        $year = $this->getClaimedFile()->get(date('Y'), []);
        $day = $year[date('d')] ?? [];
        if (!in_array(strtolower($player->getName()), $day)) {
            $day[] = strtolower($player->getName());
        }
        $year[date('d')] = $day;
        $this->getClaimedFile()->set(date('Y'), $year);
        $this->getClaimedFile()->save();
    }

    /**
     * @internal
     * Checks whether the config version is the latest and updates it if it isn't
     */
    private function configUpdater()
    {
        if (!file_exists($this->getDataFolder() . 'config.yml')) {
            $this->saveResource('config.yml');
            $this->saveResource('strings.yml');
            return;
        }
        if (!$this->getConfig()->exists('config-version')) {
            $this->getLogger()->info("§eYour config wasn't the latest. AdventCalendar renamed your old config to §bconfig-old.yml §eand created a new config. Have fun!");
            rename($this->getDataFolder() . 'config.yml', $this->getDataFolder() . 'config-old.yml');
            $this->saveResource('config.yml', true);
            $this->saveResource('strings.yml', true);
            $this->reloadConfig();
        } elseif ($this->getConfig()->get('config-version') !== self::CONFIG_VERSION) {
            $configVersion = $this->getConfig()->get('config-version');
            $this->getLogger()->info("§eYour config wasn't the latest. AdventCalendar renamed your old config to §bconfig-" . $configVersion . ".yml §eand created a new config. Have fun!");
            rename($this->getDataFolder() . 'config.yml', $this->getDataFolder() . 'config-' . $configVersion . '.yml');
            rename($this->getDataFolder() . 'strings.yml', $this->getDataFolder() . 'strings-' . $configVersion . '.yml');
            $this->saveResource('config.yml');
            $this->saveResource('strings.yml');
            $this->reloadConfig();
        }
    }

}