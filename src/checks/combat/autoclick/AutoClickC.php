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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReflectionException;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function microtime;

class AutoClickC extends Check {
	private bool $canDamagable = false;

	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "C";
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageEvent) {
			$this->canDamagable = !$event->isCancelled();
		}
	}

	/**
	 * @throws ReflectionException
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (
			$playerAPI->isDigging() ||
			$playerAPI->getAttackTicks() < 40 ||
			!$playerAPI->getPlayer()->isSurvival() ||
			!$this->canDamagable
		) {
			return;
		}
		$ticks = $playerAPI->getExternalData("clicksTicks3");
		$lastClick = $playerAPI->getExternalData("lastClick3");
		if ($packet instanceof AnimatePacket && $packet->action === AnimatePacket::ACTION_SWING_ARM) {
			$current = microtime(true);
			$ticks = $playerAPI->getExternalData("clicksTicks3", 0);
			$lastClick = $playerAPI->getExternalData("lastClick3", $current);
			$diff = $current - $lastClick;

			if ($diff <= $this->getConstant("animation-diff-time")) {
				$ticks++;
				$playerAPI->setExternalData("clicksTicks3", $ticks);
				if ($ticks > $this->getConstant("animation-diff-ticks")) {
					$this->failed($playerAPI);
				}
			} else {
				$playerAPI->setExternalData("clicksTicks3", 1);
				$playerAPI->setExternalData("lastClick3", $current);
			}
			$this->debug($playerAPI, "ticks=$ticks, lastClick=$lastClick");
		}
	}
}