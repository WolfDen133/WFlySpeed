<?php

declare(strict_types=1);

namespace WolfDen133\FlySpeed;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
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
                }), 0);
            }
        }
    }

    private function internalChange (Player $for, float $value) : void
    {
        $isOp = $for->hasPermission(DefaultPermissions::ROOT_OPERATOR);

        $boolAbilities = [
            AbilitiesLayer::ABILITY_ALLOW_FLIGHT => $for->getAllowFlight(),
            AbilitiesLayer::ABILITY_FLYING => $for->isFlying(),
            AbilitiesLayer::ABILITY_NO_CLIP => !$for->hasBlockCollision(),
            AbilitiesLayer::ABILITY_OPERATOR => $isOp,
            AbilitiesLayer::ABILITY_TELEPORT => $for->hasPermission(DefaultPermissionNames::COMMAND_TELEPORT),
            AbilitiesLayer::ABILITY_INVULNERABLE => $for->isCreative(),
            AbilitiesLayer::ABILITY_MUTED => false,
            AbilitiesLayer::ABILITY_WORLD_BUILDER => false,
            AbilitiesLayer::ABILITY_INFINITE_RESOURCES => !$for->hasFiniteResources(),
            AbilitiesLayer::ABILITY_LIGHTNING => false,
            AbilitiesLayer::ABILITY_BUILD => !$for->isSpectator(),
            AbilitiesLayer::ABILITY_MINE => !$for->isSpectator(),
            AbilitiesLayer::ABILITY_DOORS_AND_SWITCHES => !$for->isSpectator(),
            AbilitiesLayer::ABILITY_OPEN_CONTAINERS => !$for->isSpectator(),
            AbilitiesLayer::ABILITY_ATTACK_PLAYERS => !$for->isSpectator(),
            AbilitiesLayer::ABILITY_ATTACK_MOBS => !$for->isSpectator(),
        ];

        $for->getNetworkSession()->sendDataPacket(UpdateAbilitiesPacket::create(new AbilitiesData(
            $isOp ? CommandPermissions::OPERATOR : CommandPermissions::NORMAL,
            $isOp ? PlayerPermissions::OPERATOR : PlayerPermissions::MEMBER,
                $for->getId(),
            [
                new AbilitiesLayer(
                AbilitiesLayer::LAYER_BASE,
                $boolAbilities,
                $value / 20,
                0.1
                )
            ]
        )));
    }
}
