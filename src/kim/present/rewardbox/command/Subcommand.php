<?php

/*
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0.0
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace kim\present\rewardbox\command;

use kim\present\rewardbox\RewardBox;
use pocketmine\command\CommandSender;

abstract class Subcommand{
	/** @var RewardBox */
	protected $plugin;

	/** @var string */
	protected $label;

	/** @var string */
	private $name;

	/** @var string[] */
	private $aliases;

	/** @var string */
	private $permission;

	/**
	 * Subcommand constructor.
	 *
	 * @param RewardBox $plugin
	 * @param string $label
	 */
	public function __construct(RewardBox $plugin, string $label){
		$this->plugin = $plugin;
		$this->label = $label;

		$config = $plugin->getConfig();
		$this->name = $config->getNested("command.children.{$label}.name");
		$this->aliases = $config->getNested("command.children.{$label}.aliases");
		$this->permission = "rewardbox.cmd.{$label}";
	}


	/**
	 * @param string $label
	 *
	 * @return bool
	 */
	public function checkLabel(string $label) : bool{
		return strcasecmp($label, $this->name) === 0 || in_array($label, $this->aliases);
	}

	/**
	 * @param CommandSender $sender
	 * @param string[]      $args = []
	 */
	public function handle(CommandSender $sender, array $args = []) : void{
		if($sender->hasPermission($this->permission)){
			$this->execute($sender, $args);
		}else{
			$sender->sendMessage($this->plugin->getLanguage()->translateString("commands.generic.permission"));
		}
	}

	/**
	 * @param CommandSender $sender
	 * @param string[]      $args = []
	 */
	public abstract function execute(CommandSender $sender, array $args = []) : void;

	/**
	 * @return string
	 */
	public function getLabel() : string{
		return $this->label;
	}

	/**
	 * @return string
	 */
	public function getName() : string{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name) : void{
		$this->name = $name;
	}

	/**
	 * @return string[]
	 */
	public function getAliases() : array{
		return $this->aliases;
	}

	/**
	 * @param string[] $aliases
	 */
	public function setAliases(array $aliases) : void{
		$this->aliases = $aliases;
	}

	/**
	 * @return string
	 */
	public function getPermission() : string{
		return $this->permission;
	}

	/**
	 * @param string $permission
	 */
	public function setPermission(string $permission) : void{
		$this->permission = $permission;
	}
}