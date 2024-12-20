<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use AppBundle\CSPro\Data\DataSettings;
use Symfony\Component\HttpFoundation\Response;

class GenerateDictionarySettings extends Command
{ 

    //Commandes permettant de créer automatiquement les configurations pour les récupérations des données en tabulaire
    //Cette commande fait des insertions dans  la table cspro_dictionary_schema
    //Pour qu'elle s'execute correctement la table cspro_dictionaries ne doit pas être vide car il faut au moins
    //un dictionnaire ajouté dans CSWeb pour avoir des configurations de récupération

    //Cette commande est toujours à exécuter (dans le CRON ou le SCHEDULLER) avant la commande de
    // synchronisation/récupération des données (CSWebProcessRunner)

    protected static $defaultName = 'app:generate-settings';
    protected static $defaultDescription = 'Generer la configuration pour la récupération des données en tabulaires';

    private $logger;
    private $pdo;
    private $dataSettings;
    private $entity;

    public function __construct(
        \AppBundle\Service\PdoHelper $pdo ,
        \Psr\Log\LoggerInterface $logger,
        \Doctrine\ORM\EntityManagerInterface $pgDashboardEntityManager = NULL
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->pdo = $pdo;
        $this->entity = $pgDashboardEntityManager->getConnection();
        //Initialisation de la configuration
        $this->dataSettings = new DataSettings($this->pdo, $this->logger);
    }


    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //var_dump($this->entity->getPassword()); die;
        $mydatabase = $this->entity->getDatabase();
        $mydatabaseHost = $this->entity->getHost();
        $mydatabaseUsername = $this->entity->getUsername();
        $mydatabasePass = $this->entity->getPassword();

        //Récupération de tous les dictionnaires de la base de données CSWeb
        $allDictSchemas = $this->getAllSettings();

        //var_dump($allDictSchemas); die();
        foreach($allDictSchemas as $oneDictSchema){


            //Setting des informations de connexion à la base de données destinataire
            //Mettre à jour en renseignant les paramêtres correctes de la base de données CSWeb
            $infoSetting = [];

            //Pour chaque dictionnaire:
            $infoSetting['id'] = $oneDictSchema['id']; /* On récupére l'id du dictionnaire dans cspro_dictionaries */
            $infoSetting['label'] = $oneDictSchema['label']; /* On récupére le label du dictionnaire dans cspro_dictionaries */
            $infoSetting['targetSchemaName'] = $mydatabase; /* Nom base de données CSWeb */
            $infoSetting['targetHostName'] = $mydatabaseHost; /* Hôte */
            $infoSetting['dbUserName'] = $mydatabaseUsername; /* Utilisateur */
            $infoSetting['dbPassword'] = $mydatabasePass; /* Mot de passe */
            $infoSetting['mapInfo'] = FALSE;
            if($oneDictSchema['targetSchemaName'] == ""){

                //Ajouter la config s'il n'existe pas
                $this->addSetting($infoSetting);

            }
            else{

                //Modifier la config s'il existe déja
                $this->updateSetting($infoSetting);
            }
        }

        // return this if there was no problem running the command
        return 0;

        // or return this if some error happened during the execution
        // return 1;
    }



    //Fonction de récupération des dictionnaires
    protected function getAllSettings() {
        $allSettings = $this->pdo->query('SELECT `cspro_dictionaries`.`id` as id, `dictionary_name`as name, dictionary_label as label,  `host_name` as targetHostName, `schema_name` as targetSchemaName,'
                        . ' `schema_user_name` as dbUserName, AES_DECRYPT(`schema_password`, \'cspro\') as dbPassword, `map_info` as mapInfo FROM `cspro_dictionaries_schema` RIGHT JOIN cspro_dictionaries'
                        . '  ON dictionary_id = cspro_dictionaries.id    ORDER BY dictionary_label')->fetchAll();
        // $allSettings = $this->dataSettings->getDataSettings();
        return $allSettings;
    }

    //Fonction de mise à jour des configurations
    protected function updateSetting($setting) {
        $label = $setting['label'];
        try {
            $settingIsAdded = $this->dataSettings->updateDataSetting($setting);

            if ($settingIsAdded === true) {
                $result['description'] = "Config modifiée pour $label";
                $result['code'] = 200;
                $response = new Response(json_encode($result), 200);
            } else {
                $result['description'] = "Echec modification config pour $label";
                $result['code'] = 500;
                $response = new Response(json_encode($result), 500);
            }
        } catch (\Exception $e) {
                $errMsg = $e->getMessage();
                $pattern = "/(?<=SQLSTATE\[HY\d{3}\]\s\[\d{4}\]).*/";
                $match =  preg_match($pattern, $errMsg, $matchStr); 
    
                if ($match > 0) {
    
                    $errMsg = $matchStr[0];
    
                }
                //var_dump($result); die($e->getMessage());
    
                $result['description'] = "Echec modification config pour $label. $errMsg";
                $result['code'] = 500;
                $response = new Response(json_encode($result), 500);
                $this->logger->error("Failed updating configuration", array("context" => (string) $e));
                return $response;
        }


    }


    //Fonction d'ajout des configurations
    protected function addSetting($setting) {
        $label = $setting['label'];
        try {
            $settingIsAdded = $this->dataSettings->addDataSetting($setting);

            if ($settingIsAdded === true) {
                $result['description'] = "Config ajoutée pour $label";
                $result['code'] = 200;
                $response = new Response(json_encode($result), 200);
            } else {
                $result['description'] = "Echec ajout config pour $label";
                $result['code'] = 500;
                $response = new Response(json_encode($result), 500);
            }
        } catch (\Exception $e) {
                $errMsg = $e->getMessage();
                $pattern = "/(?<=SQLSTATE\[HY\d{3}\]\s\[\d{4}\]).*/";
                $match =  preg_match($pattern, $errMsg, $matchStr); 
    
                if ($match > 0) {
    
                    $errMsg = $matchStr[0];
    
                }
                //var_dump($result); die($e->getMessage());
    
                $result['description'] = "Echec ajout config pour $label. $errMsg";
                $result['code'] = 500;
                $response = new Response(json_encode($result), 500);
                $this->logger->error("Failed adding configuration", array("context" => (string) $e));
                return $response;
        }


    }

}


