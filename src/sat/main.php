<?php

namespace sat;

use pocketmine\Server;
use pocketmine\player\Player;

use pocketmine\plugin\PluginBase;
use pocketmine\Command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\ItemFactory;
use sat\libs\Vecnavium\FormsUI\SimpleForm;
use sat\libs\Vecnavium\FormsUI\CustomForm;
use sat\libs\Vecnavium\FormsUI\ModalForm;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;

use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener
{
    public function onEnable(): void
    {
        $this->getLogger()->info("Plugin Çalışıyor");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        @mkdir($this->getDataFolder());
        $this->config = new Config($this->getDataFolder() . "fiyatlar.yml", Config::YAML);
    }

    public function onCommand(CommandSender $sender, Command $cmd, String $label, array $args): bool
    {
        if ($sender instanceof Player) {
            if ($cmd == "sat") {
                $this->anaForm($sender);
            }
        }

        return true;
    }

    public function anaForm($player)
    {
        $name = $player->getName();

        foreach ($this->config->getAll(true) as $key) {
            explode(".", $key);
        }

        $form = new SimpleForm(function (Player $player, int $data = null) use ($name) {
            if ($data === null) {
                return true;
            }
            switch ($data) {
                case 0:
                    $item = $player->getInventory()->getItemInHand();
                    if (in_array($item->getId() . "." . $item->getMeta(), $this->config->getAll(true))) {
                        $isim = explode(".", $this->config->get($item->getId() . "." . $item->getMeta()))[2];
                        $fiyat = explode(".", $this->config->get($item->getId() . "." . $item->getMeta()))[0];
                        $player->getInventory()->setItemInHand(VanillaBlocks::AIR()->asItem());
                        $this->sat($player, $fiyat, (int)$item->getCount());
                        $player->sendMessage("§aElinizdeki " . $item->getCount() . " Tane §7" . $isim . " " . (int)$fiyat * $item->getCount() . "TL §aKarşılığında Satıldı!");
                    } else {
                        $player->sendMessage("§cElindeki Eşya Satılamıyor!!");
                    }
                    break;
                case 1:
                    $this->secerekSatForm($player);
                    break;
                case 2:
                    $this->onayForm($player);
                    break;
                case 3:
                    $this->onayForm($player, "cubuk");
                    break;
                case 4:
                    $this->fiyatForm($player, "ana");
                    break;
            }
        });

        $form->setTitle("§l§6Sat Menüsü");
        $form->setContent("");
        $form->addButton("Elindeki Eşyayı Sat");
        $form->addButton("Seçerek Sat");
        $form->addButton("Tüm Eşyaları Sat");
        $form->addButton("Satış Çubuğu");
        $form->addButton("Fiyat Listesi");

        $form->sendToPlayer($player);
        return $form;
    }

    public function sat($player, $fiyat, $miktar)
    {
        EconomyAPI::getInstance()->addmoney($player, (int)$fiyat * $miktar);
    }

    public function secerekSatForm($player, $secilen = "")
    {
        $form = new CustomForm(function (Player $player, array $data = null) use ($secilen) {
            if ($data === null) {
                return true;
            }

            $list = [];
            $list2 = [];
            $sayi = 0;
            foreach ($player->getInventory()->getContents(true) as $index => $item) {
                if (in_array($item->getId() . "." . $item->getMeta(), $this->config->getAll(true))) {
                    $list[] = $item->getId() . "." . $item->getMeta();
                    $list2[$item->getId() . "." . $item->getMeta()] = $sayi . "." . $item->getCount();
                }
                $sayi++;
            }

            if ($secilen == "") {
                if (!count($list) == 0) {
                    $index = $data[1];
                    $this->secerekSatForm($player, $list[$index]);
                }
            } else {
                if (is_numeric($data[4])) {
                    $data[4] = (int)$data[4];
                    $item = (new ItemFactory())->get(explode(".", $secilen)[0], explode(".", $secilen)[1], explode(".", $list2[$secilen])[1]);
                    if (in_array($item->getId() . "." . $item->getMeta(), $this->config->getAll(true))) {
                        $isim = explode(".", $this->config->get($item->getId() . "." . $item->getMeta()))[2];
                        $fiyat = explode(".", $this->config->get($item->getId() . "." . $item->getMeta()))[0];
                        if ($item->getCount() == $data[4]) {
                            $player->getInventory()->setItem(explode(".", $list2[$secilen])[0], VanillaBlocks::AIR()->asItem());
                            $this->sat($player, $fiyat, (int)$item->getCount());
                            $player->sendMessage("§aElinizdeki " . $item->getCount() . " Tane §7" . $isim . " " . (int)$fiyat * $item->getCount() . "TL §aKarşılığında Satıldı!");
                        } else if ($item->getCount() > $data[4]) {
                            $item->setCount($item->getCount() - $data[4]);
                            $player->getInventory()->setItem(explode(".", $list2[$secilen])[0], $item);
                            $this->sat($player, $fiyat, (int)$data[4]);
                            $player->sendMessage("§aElinizdeki " . $data[4] . " Tane §7" . $isim . " " . (int)$fiyat * $data[4] . "TL §aKarşılığında Satıldı!");
                        } else {
                            $player->sendMessage("§cSizde Sadece Toplam " . $item->getCount() . " Tane Var!!");
                        }
                    } else {
                        $player->sendMessage("§cElindeki Eşya Satılamıyor!!");
                    }
                } else {
                    $player->sendMessage("§cSadece Tam Sayı Girişi Yapınız!!");
                }
            }
        });
        $list = [];
        foreach ($player->getInventory()->getContents() as $index => $item) {
            if (in_array($item->getId() . "." . $item->getMeta(), $this->config->getAll(true))) {
                $list[$item->getId() . "." . $item->getMeta()] = $item->getCount();
            }
        }
        $form->setTitle("§l§6Seçerek Sat");
        $listcik = [];
        if (count($list) != 0) {
            if ($secilen == "") {
                foreach ($list as $key => $value) {
                    $listcik[] = explode(".", $this->config->get($key))[2];
                }
                $form->addLabel("Satmak İstediğiniz Eşyayı Seçiniz.");
                $form->addDropdown("Eşyalar:", $listcik);
            } else {
                $form->addLabel("Satılan Eşya => " . explode(".", $this->config->get($secilen))[2]);
                $form->addLabel("Herbir Fiyatı => " . explode(".", $this->config->get($secilen))[0]);
                $form->addLabel("Sende Olan => " . $list[$secilen]);
                $form->addLabel("Satmak İstediğiniz Miktarı Giriniz.");
                $form->addInput("Miktar Gir", $list[$secilen]);
            }
        } else {
            $form->addLabel("§cEnvanterinizde Satılabilecek Hiçbir Eşya Bulunamadı!");
        }
        $form->sendToPlayer($player);
    }

    public function fiyatForm($player, $type)
    {
        $form = new SimpleForm(function ($player, int $data = null) use ($type) {
            if ($data === null) {
                return true;
            }
            if ($type == "ana") {
                switch ($data) {
                    case 0:
                        $this->fiyatForm($player, "Maden");
                        break;
                    case 1:
                        $this->fiyatForm($player, "Taş");
                        break;
                    case 2:
                        $this->fiyatForm($player, "Odun");
                        break;
                    case 3:
                        $this->fiyatForm($player, "Tarım");
                        break;
                    case 4:
                        $this->fiyatForm($player, "Cevher");
                        break;
                    case 5:
                        $this->anaForm($player);
                        break;
                }
            } else {
                switch ($data) {
                    case 0:
                        $this->fiyatForm($player, "ana");
                        break;
                }
            }
        });
        $form->setTitle("§l§6Fiyat Listesi");
        $content = "";
        if ($type == "ana") {
            $content = "Aşağıda Satabileceğiniz Eşyaların Fiyatlarını Görüyorsunuz.";
            $form->addButton("Madenler", 0, "textures/items/diamond");
            $form->addButton("Taşlar", 0, "textures/blocks/stone");
            $form->addButton("Odunlar", 0, "textures/blocks/log_oak");
            $form->addButton("Tarım", 0, "textures/items/wheat");
            $form->addButton("Cevherler", 0, "textures/blocks/iron_ore");
        } else if ($type == "Maden") {
            foreach ($this->config->getAll(true) as $key) {
                $config = explode(".", $this->config->get($key));
                if ("Maden" == $config[1]) {
                    $content = $content . "§r§d>>§r $config[2] §l§5=> §r§7$config[0]TL\n\n";
                }
            }
        } else if ($type == "Taş") {
            foreach ($this->config->getAll(true) as $key) {
                $config = explode(".", $this->config->get($key));
                if ("Taş" == $config[1]) {
                    $content = $content . "§r§d>>§r $config[2] §l§5=> §r§7$config[0]TL\n\n";
                }
            }
        } else if ($type == "Odun") {
            foreach ($this->config->getAll(true) as $key) {
                $config = explode(".", $this->config->get($key));
                if ("Odun" == $config[1]) {
                    $content = $content . "§r§d>>§r $config[2] §l§5=> §r§7$config[0]TL\n\n";
                }
            }
        } else if ($type == "Tarım") {
            foreach ($this->config->getAll(true) as $key) {
                $config = explode(".", $this->config->get($key));
                if ("Tarım" == $config[1]) {
                    $content = $content . "§r§d>>§r $config[2] §l§5=> §r§7$config[0]TL\n\n";
                }
            }
        } else if ($type == "Cevher") {
            foreach ($this->config->getAll(true) as $key) {
                $config = explode(".", $this->config->get($key));
                if ("Cevher" == $config[1]) {
                    $content = $content . "§r§d>>§r $config[2] §l§5=> §r§7$config[0]TL\n\n";
                }
            }
        }
        $form->setContent($content . "\n\n");
        $form->addButton("§l§cGERİ");
        $form->sendToPlayer($player);
    }

    public function onayForm($player, $type = "")
    {
        $form = new ModalForm(function ($player, bool $data) use ($type) {
            if ($data === null) return true;

            if ($data == true) {
                if ($type == "cubuk") {
                    $para = EconomyAPI::getInstance()->mymoney($player);
                    if ($para >= 1000) {
                        $esya = ItemFactory::getInstance()->get(369, 0, 1); // id meta count
                        $büyü1 = new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 1);
                        $esya->addEnchantment($büyü1);
                        $esya->setCustomName("§l§6Satış Çubuğu");
                        $lore = ["§r§l§8[§r§e⚡250⚡§l§8]", "SATIŞ ÇUBUĞU", "§7(Satış Yapabilmek İçin Bir Sandığa Tıklayın)"];
                        $esya->setLore($lore);
                        EconomyAPI::getInstance()->reducemoney($player, 1000);
                        $player->getInventory()->addItem($esya);
                        $player->sendMessage("§aBaşarıyla Satış Çubuğunu Satın Aldınız.");
                    } else {
                        $player->sendMessage("§cYeterli Paranız Bulunmuyor! Gerekli Miktar: " . 1000 - $para . "TL");
                    }
                } else {
                    $basarili = [];
                    foreach ($player->getInventory()->getContents() as $index => $item) {
                        if (in_array($item->getId() . "." . $item->getMeta(), $this->config->getAll(true))) {
                            $isim = explode(".", $this->config->get($item->getId() . "." . $item->getMeta()))[2];
                            $fiyat = explode(".", $this->config->get($item->getId() . "." . $item->getMeta()))[0];
                            $basarili[] = true;
                            $this->sat($player, $fiyat, (int)$item->getCount());
                            $player->getInventory()->setItem($index, VanillaBlocks::AIR()->asItem());
                            $player->sendMessage("§aEnvanterinizdeki " . $item->getCount() . " Tane §7" . $isim . " " . (int)$fiyat * $item->getCount() . "TL §aKarşılığında Satıldı!");
                        }
                    }
                    if (!in_array(true, $basarili)) {
                        $player->sendMessage("§cEnvanterinde Satılabilecek Eşya Yok!");
                    }
                }
            }
            if ($data == false) {
                $player->sendMessage("§cİptal Edildi!");
                return true;
            }
        });
        $form->setTitle("Emin Misiniz?");
        if ($type == "cubuk") {
            $form->setContent("Satış Çubuğunu Alamak İstediğinize Emin Misiniz?");
        }else{
            $form->setContent("Envanterinizdeki Tüm Eşyaları Satmak İstediğinize Emin Misiniz?");
        }
        $form->setButton1("§l§2Evet");
        $form->setButton2("§l§4Hayır");
        $form->sendToPlayer($player);
    }

    public function cubukUse(PlayerInteractEvent $e)
    {
        $player = $e->getPlayer();
        $block = $e->getBlock();
        $item = $e->getItem();

        if ($e->getAction() == 1) {
            if (in_array("SATIŞ ÇUBUĞU", $item->getLore())) {
                if ((int)explode("⚡", $item->getLore()[0])[1] > 0) {
                    if ($block->getName() == "Chest") {
                        $e->cancel();

                        $x = $block->getPosition()->getX();
                        $y = $block->getPosition()->getY();
                        $z = $block->getPosition()->getZ();

                        $chest = $player->getWorld()->getTile(new Vector3($x, $y, $z));

                        $basarili = [];
                        $miktar = 0;
                        foreach ($chest->getInventory()->getContents() as $index => $item) {
                            if (in_array($item->getId() . "." . $item->getMeta(), $this->config->getAll(true))) {
                                $isim = explode(".", $this->config->get($item->getId() . "." . $item->getMeta()))[2];
                                $fiyat = explode(".", $this->config->get($item->getId() . "." . $item->getMeta()))[0];
                                $basarili[] = true;
                                $this->sat($player, $fiyat, (int)$item->getCount());
                                $miktar = $miktar + (int)$item->getCount();
                                $chest->getInventory()->setItem($index, VanillaBlocks::AIR()->asItem());
                                $player->sendMessage("§aSandığınızdan " . $item->getCount() . " Tane §7" . $isim . " " . (int)$fiyat * $item->getCount() . "TL §aKarşılığında Satıldı!");
                            }
                        }
                        if (!in_array(true, $basarili)) {
                            $player->sendMessage("§cSandıkta Satılabilecek Eşya Yok!");
                        } else {
                            $player->getInventory()->setItemInHand(VanillaBlocks::AIR()->asItem());
                            $item = $e->getItem();
                            $lore = ["§r§l§8[§r§e⚡" . (int)explode("⚡", $item->getLore()[0])[1] - (int)($miktar / 64) . "⚡§l§8]", "SATIŞ ÇUBUĞU", "§7(Satış Yapabilmek İçin Bir Sandığa Tıklayın)"];
                            $item->setLore($lore);
                            $player->getInventory()->setItemInHand($item);
                        }
                    } else {
                        $player->sendActionBarMessage("§cSadece Bir Sandığa Tıklayınız!!");
                    }
                } else {
                    $e->cancel();
                    $player->sendActionBarMessage("§cSATIŞ ÇUBUĞUNUN ŞARJI BİTTİ!");
                }
            }
        }
    }

    public function sarjEkle(Player $player, $miktar)
    {
        $item = $player->getInventory()->getItemInHand();

        if (in_array("SATIŞ ÇUBUĞU", $item->getLore())) {
            $lore = ["§r§l§8[§r§e⚡" . (int)explode("⚡", $item->getLore()[0])[1] + (int)$miktar . "⚡§l§8]", "SATIŞ ÇUBUĞU", "§7(Satış Yapabilmek İçin Bir Sandığa Tıklayın)"];
            $item->setLore($lore);
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage("§aElinizdeki §fSatış Çubuğuna §e$miktar §aŞarj Eklendi.");
        }
    }
}
