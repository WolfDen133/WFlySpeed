<?php

declare(strict_types=1);

namespace WolfDen133\FlySpeed;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\network\mcpe\protocol\types\UpdateAbilitiesPacketLayer;
use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    /** @var float[] */
    private array $playerSpeeds = [];

    private array $list = [];

    protected function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getServer()->getCommandMap()->register("FlySpeed", new FlySpeedCommand($this));

        $config = new Config($this->getDataFolder() . "flyspeeds", Config::JSON);

        $this->playerSpeeds = $config->getAll();
    }

    protected function onDisable(): void
    {
        $config = new Config($this->getDataFolder() . "flyspeeds", Config::JSON);

        $config->setAll($this->playerSpeeds);
        $config->save();
    }

    /**
     * @param Player $player    Player that you are updating the fly speed for
     * @param float $value      Value of the flyspeed (default 1)
     * @return void
     */
    public function updateFlySpeed (Player $player, float $value) : void
    {
        $this->playerSpeeds[$player->getUniqueId()->toString()] = $value;

        $this->internalChange($player, $value);
    }

    public function onDataPacketSendEvent (DataPacketSendEvent $event) : void
    {
        foreach ($event->getPackets() as $packet) {
            if ($packet instanceof UpdateAbilitiesPacket) {
                $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($event) : void
                {
                    foreach ($event->getTargets() as $target) {
                        if (!$target->isConnected()) return;
                        if (isset($this->list[$target->getPlayer()->getUniqueId()->toString()])) {
                            unset($this->list[$target->getPlayer()->getUniqueId()->toString()]);
                            continue;
                        }

                        if (!isset($this->playerSpeeds[$target->getPlayer()->getUniqueId()->toString()])) {
                            $this->playerSpeeds[$target->getPlayer()->getUniqueId()->toString()] = 1;
                            return;
                        }

                        $this->internalChange($target->getPlayer(), $this->playerSpeeds[$target->getPlayer()->getUniqueId()->toString()]);

                        $this->list[$target->getPlayer()->getUniqueId()->toString()] = true;
                    }
                }), 4);
            }
        }
    }

    private function internalChange (Player $for, float $value) : void
    {
        $isOp = $for->hasPermission(DefaultPermissions::ROOT_OPERATOR);

        $boolAbilities = [
            UpdateAbilitiesPacketLayer::ABILITY_ALLOW_FLIGHT => $for->getAllowFlight(),
            UpdateAbilitiesPacketLayer::ABILITY_FLYING => $for->isFlying(),
            UpdateAbilitiesPacketLayer::ABILITY_NO_CLIP => !$for->hasBlockCollision(),
            UpdateAbilitiesPacketLayer::ABILITY_OPERATOR => $isOp,
            UpdateAbilitiesPacketLayer::ABILITY_TELEPORT => $for->hasPermission(DefaultPermissionNames::COMMAND_TELEPORT),
            UpdateAbilitiesPacketLayer::ABILITY_INVULNERABLE => $for->isCreative(),
            UpdateAbilitiesPacketLayer::ABILITY_MUTED => false,
            UpdateAbilitiesPacketLayer::ABILITY_WORLD_BUILDER => false,
            UpdateAbilitiesPacketLayer::ABILITY_INFINITE_RESOURCES => !$for->hasFiniteResources(),
            UpdateAbilitiesPacketLayer::ABILITY_LIGHTNING => false,
            UpdateAbilitiesPacketLayer::ABILITY_BUILD => !$for->isSpectator(),
            UpdateAbilitiesPacketLayer::ABILITY_MINE => !$for->isSpectator(),
            UpdateAbilitiesPacketLayer::ABILITY_DOORS_AND_SWITCHES => !$for->isSpectator(),
            UpdateAbilitiesPacketLayer::ABILITY_OPEN_CONTAINERS => !$for->isSpectator(),
            UpdateAbilitiesPacketLayer::ABILITY_ATTACK_PLAYERS => !$for->isSpectator(),
            UpdateAbilitiesPacketLayer::ABILITY_ATTACK_MOBS => !$for->isSpectator(),
        ];

        $for->getNetworkSession()->sendDataPacket(UpdateAbilitiesPacket::create(
            $isOp ? CommandPermissions::OPERATOR : CommandPermissions::NORMAL,
            $isOp ? PlayerPermissions::OPERATOR : PlayerPermissions::MEMBER,
            $for->getId(),
            [
                new UpdateAbilitiesPacketLayer(UpdateAbilitiesPacketLayer::LAYER_BASE, $boolAbilities, $value / 20, 0.1),
            ]
        ));
    }
}
