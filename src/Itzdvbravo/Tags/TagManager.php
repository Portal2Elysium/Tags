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

    /** @var Tag[] */
    public $tags = [];

    public function onEnable(){
        $this->loadTags();
        $this->purechat = $this->getServer()->getPluginManager()->getPlugin("PureChat");
        $this->pureperm = $this->getServer()->getPluginManager()->getPlugin("PurePerms");

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    private function loadTags() : void{
        if(!file_exists($this->getDataFolder() . "config.yml")){
            $this->saveResource("config.yml");
        }
        self::$config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if(self::$config->get("config-version", -1) !== self::CONFIG_VERSION){
            $this->convertOldConfig();
        }
        foreach(self::$config->get("tags") as $tagId => $tagData){
            if(!isset($tagData["perm"]) or !isset($tagData["tag"])){
                $this->getLogger()->error("Tag data for $tagId is missing.  Please check the config.");
                continue;
            }
            $this->tags[$tagId] = new Tag($tagId, $tagData["tag"], $tagData["perm"]);
        }
    }

    /**
     * @return Tag[]
     */
    public function getTags() : array{
        return $this->tags;
    }

    /**
     * @param string $id
     * @return Tag|null
     */
    public function getTag(string $id) : ?Tag{
        return $this->tags[$id] ?? null;
    }

    /**
     * @param Player $player
     * @param        $tag
     * @param Item   $item
     */
    public function giveTagPermission(Player $player, Tag $tag, Item $item){
        if($player->hasPermission($tag->getPermissionNode())){
            $player->sendMessage(TextFormat::RED . "You Already Have§r " . $tag->getTagText());
        }else{
            $this->pureperm->getUserDataMgr()->setPermission($player, $tag->getPermissionNode(), null);
            $player->sendMessage("§eYou have been given §r". $tag->getTagText() ." \n§aUse /tag to equip it");
            $player->getInventory()->remove($item);
        }
    }

    /**
     * @param Player $player
     * @param string $tag
     */
    public function giveTagItem(Player $player, Tag $tag){
        $item = Item::get(Item::CLOCK, 0, 1);
        $item->setCustomName(TextFormat::RESET . $tag->getTagText() . TextFormat::YELLOW . " Tag");
        $item->setLore([TextFormat::GREEN . "Right Click To Obtain It"]);
        $nbt = $item->getNamedTag();
        $nbt->setString("tag", $tag->getId());
        $item->setNamedTag($nbt);
        $player->getInventory()->addItem($item);
        $player->sendMessage(TextFormat::GREEN . "You have gotten " . $tag->getTagText() . TextFormat::GREEN . " tag");
    }

    /**
     * @param Player $player
     */
    public function giveRandomTagItem(Player $player){
        $selectedIndex = array_rand($this->tags);
        $this->giveTagItem($player, $this->tags[$selectedIndex]);
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
                                $this->giveRandomTagItem($person);
                                $person->sendMessage(TextFormat::GREEN . "You have gotten an random tag");
                            }else{
                                if(isset($this->tags[$args[1]])){
                                    $this->giveTagItem($person, $this->tags[$args[1]]);
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
            $tag = $this->tags[$data] ?? null;
            if($tag === null){
                $player->sendMessage(TextFormat::RED . "Something went wrong!  Please report this error.");
                $this->getLogger()->error("Tried to give player " . TextFormat::GOLD . $player->getName() . " an invalid tag (" . $data . ").");
            }
            if($player->hasPermission($tag->getPermissionNode())){
                $this->purechat->setPrefix($tag->getTagText(), $player);
                $player->sendMessage("§aTag Changed To§r " . $tag->getTagText());
            }else{
                $player->sendMessage("§4You don't have permission to use this tag");
            }
        });
        $form->setTitle("§aTags");
        $form->setContent("§eChoose Your Tag");
        $lock = TextFormat::RED . '§l§cLOCKED';
        $avaible = TextFormat::GREEN . '§l§aAVAILABLE';
        foreach($this->tags as $id => $tag){
            if($player->hasPermission($tag->getPermissionNode())){
                $form->addButton($tag->getTagText() . "\n" . "{$avaible}", -1, "", $id);
            }elseif((bool) self::$config->get("show-locked-tags", true)){
                $form->addButton($tag->getTagText() . "\n" . "{$lock}", -1, "", $id);
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
