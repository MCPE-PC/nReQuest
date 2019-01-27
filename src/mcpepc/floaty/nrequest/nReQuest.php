<?php

namespace mcpepc\floaty\nrequest;

use pocketmine\Player;
use pocketmine\command\{
  CommandSender,
  Command
};
use pocketmine\event\Listener;
use pocketmine\plugin\{
  PluginBase,
  PluginLogger
};
use pocketmine\utils\Config;

use slapper\entities\SlapperHuman;
use slapper\events\SlapperHitEvent;

use xenialdan\customui\API as UIAPI;
use xenialdan\customui\elements as UIElements;
use xenialdan\customui\event\{
  // UICloseEvent,
  UIDataReceiveEvent
};
use xenialdan\customui\windows\{
  CustomForm,
  ModalForm// ,
  // ServerForm,
  // SimpleForm
};

class nReQuest extends PluginBase implements Listener {
  private $db;
  private $logger;

  protected $configuringPlayer = [];
  protected static $uis = [
    'questStart' => [],
    'settings' => []
  ];

  function onEnable(): void {
    $this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->registerUIs();
  }
  function onLoad(): void {
    $this->logger = new PluginLogger($this);
    $this->saveDefaultConfig();
    $this->db = new Config($this->getDataFolder() . 'reQuests.json', Config::JSON);
  }
  function onDisable() {
    if ($this->db->hasChanged()) {
      $this->db->save();
    }
  }
  function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
    switch (\strtolower($command->getName())) {
      case 'nrequest':
        $name = $sender->getName();
        if (\in_array($name, $this->configuringPlayers)) {
          $this->removeConfiguringPlayer($name);
          $sender->sendMessage('Quit configuring mode');
        } else {
          $this->configuringPlayers[] = $name;
          $sender->sendMessage('Hit any Slapper Human to configure');
        }
        return true;
      default:
        return false;
    }
  }
  function onSlapperHit(SlapperHitEvent $event): void {
    $human = $event->getEntity();
    $player = $event->getDamager();
    if (!($human instanceof SlapperHuman && $player instanceof Player)) {
      return;
    }
    $entityId = $human->getId();
    if (\in_array($player->getName(), $this->configuringPlayers)) {
      if ($this->db->get($entityId)) {
        UIAPI::showUIbyID($this, self::$uis['settings'][$entityId], $player);
      } else {
        $this->db->set($entityId, [
          'slapperId' => $entityId,
          'addedBy' => $player->getName(),
          'quest' => [
            'title' => '',
            'text' => '',
            'timeLimit' => -1
          ],
          'rewards' => [
            'money' => 0,
            'items' => []
          ]
        ]);
        $player->sendMessage('Null quest created');
      }
      $this->removeConfiguringPlayer($player->getName());
    } else if ($this->db->get($entityId) && $player->hasPermission('nrequest.quest')) {
      UIAPI::showUIbyID($this, self::$uis['questStart'][$entityId], $player);
    }
  }
  function onUIDataReceiveEvent(UIDataReceiveEvent $event): void {
    if ($event->getPlugin() !== $this) {
      return;
    }
    foreach (self::$uis as $type => $array) {
      if (!\is_array($array)) {
        return;
      }
      $uiID = $event->getID();
      if ($entityId = \array_search($uiID, $array)) {
        switch ($type) {
          case 'questStart':
            $event->getPlayer()->sendMessage('Not yet supported');
            break;
          case 'settings':
            $data = $event->getData();
            break;
        }
      }
    }
  }
  function getLogger(): PluginLogger {
    return $this->logger;
  }

  protected function removeConfiguringPlayer(string $name): bool {
    $key = \array_search($name, $this->configuringPlayers, true);
    if ($key !== false) {
      \array_splice($this->configuringPlayers,  (int) $key, 1);
      return true;
    } else {
      return false;
    }
  }
  private function registerUIs(): void {
    foreach ($this->db->getAll() as $quest) {
      $startUI = new ModalForm('ReQuest!', $quest['quest']['title'], $quest['quest']['text'], 'Okay!', 'Cancel');
      self::$uis['questStart'][$quest['slapperId']] = UIAPI::addUI($this, $startUI);
      $settingsUI = new CustomForm('ReQuest settings');
      self::$uis['settings'][$quest['slapperId']] = UIAPI::addUI($this, $settingsUI);
    }
  }
}
