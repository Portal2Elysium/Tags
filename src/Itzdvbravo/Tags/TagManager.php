<?php

namespace Itzdvbravo\Tags;

use _64FF00\PureChat\PureChat;
use _64FF00\PurePerms\PurePerms;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class TagManager extends PluginBase{

    const CONFIG_VERSION = 2;

    /** @var Config */
    public static $config;

    /** @var PureChat */
    public $purechat;

    /** @var PurePerms */
    public $pureperm;

    public function onEnable(){
        if(!file_exists($this->getDataFolder() . "config.yml")){
            $this->saveResource("config.yml");
        }
        self::$config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if(self::$config->get("config-version", -1) !== self::CONFIG_VERSION){
            $this->convertOldConfig();
        }

        $this->purechat = $this->getServer()->getPluginManager()->getPlugin("PureChat");
        $this->pureperm = $this->getServer()->getPluginManager()->getPlugin("PurePerms");

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    /**
     * @param Player $player
     * @param        $tag
     * @param Item   $item
     */
    public function giveTag(Player $player, $tag, Item $item){
        $cfg = self::$config->getNested("tags.". $tag);
        $tagPerm = $cfg["perm"];
        if($player->hasPermission($tagPerm)){
            $player->sendMessage(TextFormat::RED . "You Already Have§r {$cfg["tag"]}");
        }else{
            $this->pureperm->getUserDataMgr()->setPermission($player, $tagPerm, null);
            $player->sendMessage("§eYou have been given §r{$cfg["tag"]} \n§aUse /tag to equip it");
            $player->getInventory()->remove($item);
        }
    }

    /**
     * @param Player $player
     * @param string $tag
     */
    public function addTag(Player $player, string $tag){
        $cfg = self::$config->getNested("tags." . $tag);
        $item = Item::get(Item::CLOCK, 0, 1);
        $item->setCustomName(TextFormat::RESET . "{$cfg["tag"]}" . TextFormat::YELLOW . " Tag");
        $item->setLore([TextFormat::GREEN . "Right Click To Obtain It"]);
        $nbt = $item->getNamedTag();
        $nbt->setString("tag", $tag);
        $item->setNamedTag($nbt);
        $player->getInventory()->addItem($item);
        $player->sendMessage(TextFormat::GREEN . "You have gotten {$cfg["tag"]} " . TextFormat::GREEN . "tag");
    }

    /**
     * @param Player $player
     */
    public function addRandomTag(Player $player){
        $selectedIndex = array_rand(self::$config->get("tags"));
        $this->addTag($player, $selectedIndex);
    }

    /**
     * @param CommandSender $player
     * @param Command       $cmd
     * @param string        $label
     * @param array         $args
     * @return bool
     */
    public function onCommand(CommandSender $player, Command $cmd, string $label, array $args) : bool{
        switch($cmd->getName()){
            case "tag":
                if($player->hasPermission("tags.use")){
                    if($player instanceof Player){
                        $this->openForm($player);
                    }else{
                        $player->sendMessage(TextFormat::RED . "Use this in game");
                    }
                }else{
                    $player->sendMessage(TextFormat::RED . "Insufficient permission");
                }
                break;
            case "givetag":
                if($player->hasPermission("tags.give")){
                    if(!empty($args[0])){
                        $person = Server::getInstance()->getPlayer($args[0]);
                        if($person !== Null){
                            if(empty($args[1])){
                                $this->addRandomTag($person);
                                $person->sendMessage(TextFormat::GREEN . "You have gotten an random tag");
                            }else{
                                if((self::$config->getNested("tag." . $args[1], false))){
                                    $this->addTag($person, $args[1]);
                                }else{
                                    $player->sendMessage(TextFormat::GOLD . "Tag doesn't exist");
                                }
                            }
                        }else{
                            $player->sendMessage(TextFormat::RED . "Player not found");
                        }
                    }else{
                        $player->sendMessage(TextFormat::RED . "Provide a player");
                    }
                }else{
                    $player->sendMessage(TextFormat::RED . "Insufficient permission");
                }
                break;
        }
        return true;
    }

    /**
     * @param Player $player
     * @return mixed
     */
    public function openForm(Player $player){
        $form = new SimpleForm(function(Player $player, $data = NULL){
            if($data === null){
                return;
            }
            $cfg = self::$config->getNested("tags." . $data);
            $permCheck = $cfg["perm"];
            $tag = $cfg["tag"];
            $realTag = " {$tag} ";
            if($player->hasPermission($permCheck)){
                $this->purechat->setPrefix($realTag, $player);
                $player->sendMessage("§aTag Changed To§r $tag");
            }else{
                $player->sendMessage("§4You don't have permission to use this tag");
            }
        });
        $form->setTitle("§aTags");
        $form->setContent("§eChoose Your Tag");
        $tags = self::$config->get("tags");
        $lock = TextFormat::RED . '§l§cLOCKED';
        $avaible = TextFormat::GREEN . '§l§aAVAILABLE';
        foreach($tags as $id => $tagData){
            if($player->hasPermission($tagData["perm"])){
                $form->addButton("{$tagData["tag"]}" . "\n" . "{$avaible}", -1, "", $id);
            }elseif((bool) self::$config->get("show-locked-tags", true)){
                $form->addButton("{$tagData["tag"]}" . "\n" . "{$lock}", -1, "", $id);
            }
        }
        $form->sendToPlayer($player);
        return $form;
    }

    private function convertOldConfig(){

        if(self::$config->get("config-version", -1) < 0){
            $tags = [];
            foreach(self::$config->getAll() as $tagName => $tagData){
                $tags[$tagName]["perm"] = $tagData[0] ?? "";
                $tags[$tagName]["tag"] = $tagData[1] ?? "";
                self::$config->remove($tagName);
            }
            self::$config->set("show-locked-tags", true);
            self::$config->set("tags", $tags);
        }
        self::$config->set("config-version", 2);
        self::$config->save();
    }
}
