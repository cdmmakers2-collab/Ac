<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\blockinteract;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerInteractEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

class BlockReach extends Check {
	public function getName() : string {
		return "BlockReach";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerInteractEvent) {
			$block = $event->getBlock();
			$player = $playerAPI->getPlayer();
			$reach = $player->isCreative() ? $this->getConstant("max-creative-reach") : $this->getConstant("max-survival-reach");
			
			// Add buffer for network latency
			$reach += 0.3;
			
			// Add tolerance for speed effect (scale by level)
			if ($player->getEffects()->has(VanillaEffects::SPEED())) {
				$speedLevel = $player->getEffects()->get(VanillaEffects::SPEED())->getEffectLevel();
				$reach += 0.15 * min($speedLevel, 5);
			}
			
			if (!$player->canInteract($block->getPosition()->add(0.5, 0.5, 0.5), $reach)) {
				$this->failed($playerAPI);
			}
		}
	}
}