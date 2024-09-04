<?php

namespace antaksio\CBP;

use pocketmine\block\VanillaBlocks;
use pocketmine\event\Listener;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\block\BlockTypeIds;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\transaction\InvMenuTransaction;

class Main extends PluginBase implements Listener
{
    private array $openMenus = [];

    public function onEnable(): void
    {
        // Vérifie si le gestionnaire InvMenu est bien enregistré
        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("§aLe plugin Cobble Breaker est activé!");
    }

    public function openGUI(Player $player): void
    {
        // Crée un menu de type Hopper
        $menuc = InvMenu::create(InvMenu::TYPE_HOPPER);
        $menuc->setName("§l§cCobble Breaker");

        // Sauvegarde du menu pour l'accès ultérieur
        $this->openMenus[$player->getName()] = $menuc;

        // Gestionnaire de transaction pour le menu
        $menuc->setListener(function (InvMenuTransaction $transaction) use ($player): InvMenuTransactionResult {
            $itemClicked = $transaction->getItemClicked();

            // Debug: Envoyer un message pour confirmer l'item cliqué
            $player->sendMessage("Vous avez cliqué sur un item: " . $itemClicked->getName());

            return $transaction->continue(); // Permet la transaction
        });

        // Envoie le menu au joueur
        $menuc->send($player);
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $blockId = $block->getTypeId();

        // Vérifie si le bloc cliqué est une glowstone
        if ($blockId === BlockTypeIds::GLOWSTONE) {
            // Ouvre le GUI
            $this->openGUI($player);
        } else {
            // Debug : Affiche un message au joueur pour confirmer que le bloc n'est pas une Glowstone
            return;
        }
    }

    public function onInventoryClose(InventoryCloseEvent $event): void
    {
        $player = $event->getPlayer();
        $inventory = $event->getInventory();

        // Vérifie si l'inventaire fermé est celui que nous avons ouvert
        if (isset($this->openMenus[$player->getName()]) && $this->openMenus[$player->getName()]->getInventory() === $inventory) {
            $cobblestoneCount = 0;

            // Compte le nombre total de cobblestones dans le GUI
            foreach ($inventory->getContents() as $item) {
                if ($item->equals(VanillaBlocks::COBBLESTONE()->asItem())) {
                    $cobblestoneCount += $item->getCount();
                }
            }

            // Vérifie si le joueur a au moins 256 cobblestones dans le GUI
            if ($cobblestoneCount >= 256) {
                // Retire 256 cobblestones du GUI
                $remainingToRemove = 256;
                foreach ($inventory->getContents() as $slot => $item) {
                    if ($item->equals(VanillaBlocks::COBBLESTONE()->asItem())) {
                        $currentCount = $item->getCount();
                        if ($currentCount <= $remainingToRemove) {
                            // Retire l'item complet
                            $inventory->setItem($slot, VanillaItems::AIR());
                            $remainingToRemove -= $currentCount;
                        } else {
                            // Retire seulement la quantité nécessaire
                            $item->setCount($currentCount - $remainingToRemove);
                            $inventory->setItem($slot, $item);
                            $remainingToRemove = 0;
                        }

                        if ($remainingToRemove <= 0) {
                            break;
                        }
                    }
                }

                // Transforme la cobblestone en plusieurs items
                $player->sendMessage("La cobblestone a été transformée en plusieurs items !");
                $items = [
                    VanillaItems::IRON_INGOT(),
                    VanillaItems::DIAMOND(),
                    VanillaItems::EMERALD(),
                    VanillaItems::GOLD_INGOT(),
                    VanillaItems::NETHERITE_INGOT()
                ];

                foreach ($items as $item) {
                    $count = random_int(1, 10);
                    $item->setCount($count);
                    $player->getInventory()->addItem($item);
                }

            } else {
                // Si le joueur n'a pas assez de cobblestone, envoyer un message et rendre les items
                $player->sendMessage("§cVous n'avez pas assez de cobblestones. Il vous en faut au moins 256.
                ou bien vous avez pas placer de cobblestones");



                // Rend tous les items présents dans le GUI au joueur
                foreach ($inventory->getContents() as $item) {
                    if (!$item->isNull()) {
                        $player->getInventory()->addItem($item);
                    }
                }
            }

            // Supprime le menu de la liste des menus ouverts
            unset($this->openMenus[$player->getName()]);
        }
    }
}
