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

namespace ReinfyTeam\Zuri\checks\combat\autoclick;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use ReflectionException;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;

class AutoClickA extends Check {
	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws ReflectionException
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEvent::ATTACK_NODAMAGE) {
			// Primary detection: High CPS with perfect consistency
			if ($player->isSurvival() && $playerAPI->getCPS() > $this->getConstant("max-cps")) {
				if ($playerAPI->getAttackTicks() < 5) {
					$this->debug($playerAPI, "HIGH_CPS=" . $playerAPI->getCPS());
					$this->failed($playerAPI);
					return;
				}
			}

			// Secondary detection: Deviation pattern matching
			$ticks = $playerAPI->getExternalData("ticksClick", 0);
			$avgSpeed = $playerAPI->getExternalData("avgSpeed", 0);
			$avgDeviation = $playerAPI->getExternalData("avgDeviation", 0);

			$playerAPI->setExternalData("ticksClick", 0);
			if ($ticks > 0 && $avgSpeed > 0 && $avgDeviation > 0) {
				if (!$playerAPI->isDigging() && $ticks <= $this->getConstant("max-ticks")) {
					$playerAPI->setExternalData("ticksClick", $ticks + 1);
					$speed = $ticks * 50;
					$playerAPI->setExternalData("avgSpeed", (($avgSpeed * 14) + $speed) / 15);
					$deviation = abs($speed - $playerAPI->getExternalData("avgSpeed"));
					$playerAPI->setExternalData("avgDeviation", (($avgDeviation * 9) + $deviation) / 10);

					// Detect extremely consistent click patterns (automated)
					if ($ticks > 3 && $playerAPI->getExternalData("avgDeviation") < $this->getConstant("max-deviation") && $playerAPI->getCPS() > 15) {
						$this->debug($playerAPI, "BOT_PATTERN: deviation=" . $playerAPI->getExternalData("avgDeviation"));
						$this->failed($playerAPI);
					}
				} else {
					$playerAPI->setExternalData("ticksClick", 0);
					$playerAPI->setExternalData("avgSpeed", 0);
					$playerAPI->setExternalData("avgDeviation", 0);
				}
			} else {
				$playerAPI->setExternalData("avgSpeed", 50);
				$playerAPI->setExternalData("avgDeviation", 0);
				$playerAPI->setExternalData("ticksClick", 1);
			}
		}
	}
}