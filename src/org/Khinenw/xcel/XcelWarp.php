<?php

/*
	\-\     /-/  .          .              . |--|
	 \ \   / /   .   /----| .    / /--\ \  . |  |
	  \ \ / /    .  /  ---| .   / /----\ \ . |  |
	   \   /     . /  /     .   | |------| . |  |
	   /   \     . \  \     .   | |        . |  |
	  / / \ \    .  \  ---| .   \ \-----|  . |  |
	 / /   \ \   .   \----| .    \------|  . |  |
	/-/     \-\  .          .              . |--|
	          Welcome to Xceled Ngine
                   by Khinenw
        "Convenient, Stable, yet Extendable"
            "This project applies GPLv3"
 */

namespace org\Khinenw\xcel;

use Khinenw\XcelUpdater\UpdatePlugin;
use Khinenw\XcelUpdater\XcelUpdater;
use org\Khinenw\xcel\event\GameStatusUpdateEvent;
use org\Khinenw\xcel\event\PlayerRecalculationEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\Server;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;

class XcelWarp extends UpdatePlugin implements Listener{

	public static $warpData = [];

	public function onEnable(){
		@mkdir($this->getDataFolder());

		XcelUpdater::chkUpdate($this);
		if(!is_file($this->getDataFolder() . "warp.json")){
			file_put_contents($this->getDataFolder() . "warp.json", json_encode([]));
		}

		self::$warpData = json_decode(file_get_contents($this->getDataFolder() . "warp.json"), true);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onSignChange(SignChangeEvent $event){
		$lines = $event->getLines();

		if(strtoupper($lines[0]) !== "WARP") return;

		if(!$event->getPlayer()->hasPermission("warp.mk")) return;

		if(!isset(XcelNgien::$worlds[$lines[1]])){
			$event->getPlayer()->sendMessage(TextFormat::RED . "Wrong world!");
			return;
		}

		$loc = $this->encodeLoc($event->getBlock());
		self::$warpData[$loc] = [
			"world" => $lines[1]
		];

		$this->saveWarpData();
		$this->updateSigns($loc);
	}

	public function onBlockBreak(BlockBreakEvent $event){
		if(!$event->getPlayer()->hasPermission("warp.del")) return;

		$loc = $this->encodeLoc($event->getBlock());

		if(!isset(self::$warpData[$loc])) return;
		unset(self::$warpData[$loc]);

		$event->getPlayer()->sendMessage(TextFormat::AQUA . "Successfully deleted the warp.");
		$this->saveWarpData();
	}

	public function saveWarpData(){
		file_put_contents($this->getDataFolder() . "warp.json", json_encode(self::$warpData));
	}

	public static function encodeLoc(Position $location){
		return $location->getFloorX() . ";" . $location->getFloorY() . ";" . $location->getFloorZ() . ";" . $location->getLevel()->getFolderName();
	}

	public static function decodeLoc($locText){
		$loc = explode(";", $locText);

		return new Position($loc[0], $loc[1], $loc[2], Server::getInstance()->getLevelByName($loc[3]));
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$loc = $this->encodeLoc($event->getBlock());

		if(isset(self::$warpData[$loc])){
			if(!$event->getPlayer()->hasPermission("warp.join")){
				$event->getPlayer()->sendMessage(TextFormat::DARK_RED . XcelNgien::getTranslation("CANNOT_JOIN"));
				return;
			}

			$world = self::$warpData[$loc]["world"];

			if(!isset(XcelNgien::$worlds[$world])){
				$event->getPlayer()->sendMessage(TextFormat::RED . "Invalid Warp!");
				return;
			}

			$game = XcelNgien::$worlds[$world];
			if(!isset(XcelNgien::$players[$event->getPlayer()->getName()])) return;

			$player = XcelNgien::$players[$event->getPlayer()->getName()];
			if(!$game->canWarpTo($player)){
				$player->getPlayer()->sendMessage(TextFormat::RED . XcelNgien::getTranslation("CANNOT_JOIN"));
				return;
			}

			$player->getPlayer()->teleport($game->getPreparationPosition($player));
		}
	}

	public function onGameStatusUpdate(GameStatusUpdateEvent $event){
		$targetWorld = $event->getGame()->getWorld()->getFolderName();
		foreach(self::$warpData as $loc => $data){
			if($data["world"] === $targetWorld){
				self::updateSigns($loc);
			}
		}
	}

	public function onPlayerRecalculation(PlayerRecalculationEvent $event){
		$targetWorld = $event->getGame()->getWorld()->getFolderName();
		foreach(self::$warpData as $loc => $data){
			if($data["world"] === $targetWorld){
				self::updateSigns($loc);
			}
		}
	}

	public static function updateSigns($loc){
		$data = self::$warpData[$loc];
		$pos = self::decodeLoc($loc);
		$sign = $pos->getLevel()->getTile($pos);

		if(!isset(XcelNgien::$worlds[$data["world"]])){
			return;
		}

		$game = XcelNgien::$worlds[$data["world"]];

		if(!($sign instanceof Sign)){
			return;
		}

		if($game instanceof XcelRoundGame && isset($game->getConfiguration()["max-round"])){
			$sign->setText(
				TextFormat::YELLOW."[".$game->getGameName()."]",
				($game->getStatus() === XcelGame::STATUS_IN_GAME) ? TextFormat::RED."Running" : TextFormat::AQUA."Preparing",
				TextFormat::GREEN.$game->getAliveCount()." Players",
				TextFormat::GREEN.$game->getRound()."/".$game->getConfiguration()["max-round"]." Rounds"
			);
		}else{
			$sign->setText(
				TextFormat::YELLOW."[".$game->getGameName()."]",
				($game->getStatus() === XcelGame::STATUS_IN_GAME) ? TextFormat::RED."Running" : TextFormat::AQUA."Preparing",
				TextFormat::GREEN.$game->getAliveCount()." Players",
				""
			);
		}
	}

	public function compVersion($pluginVersion, $repoVersion){
		return $pluginVersion !== $repoVersion;
	}

	public function getPluginYamlURL(){
		return "https://raw.githubusercontent.com/HelloWorld017/XcelWarp/master/plugin.yml";
	}
}
