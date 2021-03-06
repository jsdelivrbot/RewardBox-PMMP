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
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace kim\present\rewardbox;

use kim\present\rewardbox\command\{CreateSubcommand, EditSubcommand, NameSubcommand, RemoveSubcommand, Subcommand};
use kim\present\rewardbox\inventory\RewardBoxInventory;
use kim\present\rewardbox\lang\PluginLang;
use kim\present\rewardbox\listener\{BlockEventListener, InventoryEventListener};
use kim\present\rewardbox\utils\HashUtils;
use pocketmine\command\{Command, CommandSender, PluginCommand};
use pocketmine\inventory\{DoubleChestInventory};
use pocketmine\level\Position;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\permission\{Permission, PermissionManager};
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Chest;

class RewardBox extends PluginBase{
	public const SUBCOMMAND_CREATE = 0;
	public const SUBCOMMAND_REMOVE = 1;
	public const SUBCOMMAND_EDIT = 2;
	public const SUBCOMMAND_NAME = 3;

	public const TAG_PLUGIN = "RewardBox";

	/** @var RewardBox */
	private static $instance;

	/** @return RewardBox */
	public static function getInstance() : RewardBox{
		return self::$instance;
	}

	/** @var PluginLang */
	private $language;

	/** @var Subcommand[] */
	private $subcommands;

	/** @var RewardBoxInventory[] */
	private $rewardBoxs = [];

	/**
	 * Called when the plugin is loaded, before calling onEnable()
	 */
	public function onLoad() : void{
		self::$instance = $this;
	}

	/**
	 * Called when the plugin is enabled
	 */
	public function onEnable() : void{
		//Save default resources
		$this->saveResource("lang/eng/lang.ini", false);
		$this->saveResource("lang/kor/lang.ini", false);
		$this->saveResource("lang/language.list", false);

		//Load config file
		$config = $this->getConfig();

		//TODO: Check latest version

		//Load language file
		$this->language = new PluginLang($this, $config->getNested("settings.language"));
		$this->getLogger()->info($this->language->translate("language.selected", [$this->language->getName(), $this->language->getLang()]));

		//Load reward boxs data
		$this->rewardBoxs = [];
		if(file_exists($file = "{$this->getDataFolder()}RewardBoxs.dat")){
			$namedTag = (new BigEndianNBTStream())->readCompressed(file_get_contents($file));
			if($namedTag instanceof CompoundTag){
				/** @var CompoundTag $tag */
				foreach($namedTag as $hash => $tag){
					$this->rewardBoxs[$hash] = RewardBoxInventory::nbtDeserialize($tag);
				}
			}else{
				$this->getLogger()->error("The file is not in the NBT-CompoundTag format : $file");
			}
		}

		//Register main command
		$command = new PluginCommand($config->getNested("command.name"), $this);
		$command->setPermission("rewardbox.cmd");
		$command->setAliases($config->getNested("command.aliases"));
		$command->setUsage($this->language->translate("commands.rewardbox.usage"));
		$command->setDescription($this->language->translate("commands.rewardbox.description"));
		$this->getServer()->getCommandMap()->register($this->getName(), $command);

		//Register subcommands
		$this->subcommands = [
			self::SUBCOMMAND_CREATE => new CreateSubcommand($this),
			self::SUBCOMMAND_REMOVE => new RemoveSubcommand($this),
			self::SUBCOMMAND_EDIT => new EditSubcommand($this),
			self::SUBCOMMAND_NAME => new NameSubcommand($this)
		];

		//Load permission's default value from config
		$permissions = PermissionManager::getInstance()->getPermissions();
		$defaultValue = $config->getNested("permission.main");
		if($defaultValue !== null){
			$permissions["rewardbox.cmd"]->setDefault(Permission::getByName($config->getNested("permission.main")));
		}
		foreach($this->subcommands as $key => $subcommand){
			$label = $subcommand->getLabel();
			$defaultValue = $config->getNested("permission.children.{$label}");
			if($defaultValue !== null){
				$permissions["rewardbox.cmd.{$label}"]->setDefault(Permission::getByName($defaultValue));
			}
		}

		//Register event listeners
		$this->getServer()->getPluginManager()->registerEvents(new BlockEventListener($this), $this);
		$this->getServer()->getPluginManager()->registerEvents(new InventoryEventListener($this), $this);
	}

	/**
	 * Called when the plugin is disabled
	 * Use this to free open things and finish actions
	 */
	public function onDisable() : void{
		//Save reward boxs data
		$namedTag = new CompoundTag();
		foreach($this->rewardBoxs as $hash => $rewardBoxInventory){
			$namedTag->setTag($rewardBoxInventory->nbtSerialize(HashUtils::positionHash($rewardBoxInventory->getHolder())));
		}
		file_put_contents("{$this->getDataFolder()}RewardBoxs.dat", (new BigEndianNBTStream())->writeCompressed($namedTag));
	}

	/**
	 * @param CommandSender $sender
	 * @param Command       $command
	 * @param string        $label
	 * @param string[]      $args
	 *
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(empty($args[0])){
			$targetSubcommand = null;
			foreach($this->subcommands as $key => $subcommand){
				if($sender->hasPermission($subcommand->getPermission())){
					if($targetSubcommand === null){
						$targetSubcommand = $subcommand;
					}else{
						//Filter out cases where more than two command has permission
						return false;
					}
				}
			}
			$targetSubcommand->handle($sender);
			return true;
		}else{
			$label = array_shift($args);
			foreach($this->subcommands as $key => $subcommand){
				if($subcommand->checkLabel($label)){
					$subcommand->handle($sender, $args);
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @Override for multilingual support of the config file
	 *
	 * @return bool
	 */
	public function saveDefaultConfig() : bool{
		$configFile = "{$this->getDataFolder()}config.yml";
		if(file_exists($configFile)){
			return false;
		}

		$resource = $this->getResource("lang/{$this->getServer()->getLanguage()->getLang()}/config.yml");
		if($resource === null){
			$resource = $this->getResource("lang/" . PluginLang::FALLBACK_LANGUAGE . "/config.yml");
		}

		$ret = stream_copy_to_stream($resource, $fp = fopen($configFile, "wb")) > 0;
		fclose($fp);
		fclose($resource);
		return $ret;
	}

	/**
	 * @return PluginLang
	 */
	public function getLanguage() : PluginLang{
		return $this->language;
	}

	/**
	 * @return Subcommand[]
	 */
	public function getSubcommands() : array{
		return $this->subcommands;
	}

	/**
	 * @return RewardBoxInventory[]
	 */
	public function getRewardBoxs() : array{
		return $this->rewardBoxs;
	}

	/**
	 * @param Position $pos
	 * @param bool     $checkSide = false
	 *
	 * @return RewardBoxInventory|null
	 */
	public function getRewardBox(Position $pos, bool $checkSide = false) : ?RewardBoxInventory{
		$rewardBox = $this->rewardBoxs[HashUtils::positionHash($pos)] ?? null;
		if($rewardBox !== null){
			return $rewardBox;
		}elseif($checkSide){
			$chest = $pos->level->getTile($pos);
			if(!$chest instanceof Chest){
				return null;
			}

			$inventory = $chest->getInventory();
			if(!$inventory instanceof DoubleChestInventory){
				return null;
			}

			foreach([$inventory->getLeftSide(), $inventory->getRightSide()] as $key => $chestInventory){
				$rewardBox = $this->rewardBoxs[HashUtils::positionHash($chestInventory->getHolder())] ?? null;
				if($rewardBox !== null){
					return $rewardBox;
				}
			}
		}
		return null;
	}

	/**
	 * @param Position $pos
	 * @param bool     $checkSide = false
	 *
	 * @return bool true if exists and successful remove, else false
	 */
	public function removeRewardBox(Position $pos, bool $checkSide = false) : bool{
		$rewardBoxInventory = $this->getRewardBox($pos, $checkSide);
		if($rewardBoxInventory === null){
			return false;
		}

		$chest = $pos->level->getTile($pos);
		if($chest instanceof Chest){
			$chest->getInventory()->setContents($rewardBoxInventory->getContents(true));
		}
		unset($this->rewardBoxs[HashUtils::positionHash($rewardBoxInventory->getHolder())]);
		return true;
	}

	/**
	 * @param Chest  $chest
	 * @param string $customName   = "RewardBox"
	 * @param int    $creationTime = null
	 *
	 * @return bool true if successful creation, else false
	 */
	public function createRewardBox(Chest $chest, string $customName = "RewardBox", int $creationTime = null) : bool{
		if($this->getRewardBox($chest, true) !== null){
			return false;
		}

		$chestInventory = $chest->getInventory();
		$this->rewardBoxs[HashUtils::positionHash($chest)] = new RewardBoxInventory($chest, $chestInventory->getContents(true), $customName, $creationTime);
		$chestInventory->clearAll();
		return true;
	}
}
