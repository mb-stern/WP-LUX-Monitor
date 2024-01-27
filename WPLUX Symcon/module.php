<?php

	class WPLUXSymcon extends IPSModule
	{

		private $updateTimer;
		protected function Log($Message)
		{
			IPS_LogMessage(__CLASS__, $Message);
		}

		public function Create()
		{
		//Never delete this line!
		parent::Create();

		$this->RegisterPropertyString('IPAddress', '192.168.178.59');
		$this->RegisterPropertyInteger('Port', 8889);
		$this->RegisterPropertyString('IDListe', '[]');
		$this->RegisterPropertyInteger('UpdateInterval', 0);
	
		// Timer für Aktualisierung registrieren
		$this->RegisterTimer('UpdateTimer', 0, 'WPLUX_Update(' . $this->InstanceID . ');');
		}

		public function Destroy()
		{
		//Never delete this line!
		parent::Destroy();
		}

		public function ApplyChanges()
		{
		//Never delete this line!
		parent::ApplyChanges();

		// Timer für Aktualisierung aktualisieren
		$this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

		// Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
		$this->Update();
		}

		public function Update()
		{
		//Verbindung zur Lux
		$IpWwc = "{$this->ReadPropertyString('IPAddress')}";
		$WwcJavaPort = "{$this->ReadPropertyInteger('Port')}";
		$SiteTitle = "WÄRMEPUMPE";

		// Integriere Variabelbeschreibung aus Java Daten
		require_once __DIR__ . '/../java_daten.php';

		// Lesen Sie die ID-Liste
		$idListe = json_decode($this->ReadPropertyString('IDListe'), true);

		// Debug-Ausgabe
		$this->Log("ID-Liste: " . print_r($idListe, true));
	
		// Variablen
		$sBuff = 0;
		$time1 = time();
		$filename = "test.tst";
		$JavaWerte = 0;
		$refreshtime = 5; // Sekunden

		// Connecten
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		$connect = socket_connect($socket, $IpWwc, $WwcJavaPort);

		if (!$connect) {
		$error_code = socket_last_error();
		exit("Socket connect failed with error code: $error_code\n");
		}
	
		// Daten holen
		$msg = pack('N*',3004);
		//printf('msg:%s <br>',$msg);
		$send=socket_write($socket, $msg, 4); //3004 senden
		//printf('Bytes send:%d <br>',$send);

		$msg = pack('N*',0);
		//printf('msg:%s <br>',ord($msg));
		$send=socket_write($socket, $msg, 4); //0 senden
		//printf('Bytes send:%d <br>',$send);

		socket_recv($socket,$Test,4,MSG_WAITALL);  // Lesen, sollte 3004 zurückkommen
		$Test = unpack('N*',$Test);
		//printf('read:%s <br>',implode($Test));

		socket_recv($socket,$Test,4,MSG_WAITALL); // Status
		$Test = unpack('N*',$Test);
		//printf('Status:%s <br>',implode($Test));

		socket_recv($socket,$Test,4,MSG_WAITALL); // Länge der nachfolgenden Werte
		$Test = unpack('N*',$Test);
		//printf('Länge der nachfolgenden Werte:%s <br>',implode($Test));

		$JavaWerte = implode($Test);
		//printf('============================================================== <br>');
	
		for ($i = 0; $i < $JavaWerte; ++$i)//vorwärts
		{
		socket_recv($socket,$InBuff[$i],4,MSG_WAITALL);  // Lesen, sollte 3004 zurückkommen
		$daten_raw[$i] = implode(unpack('N*',$InBuff[$i]));
		//printf('InBuff(%d): %d <br>',$i,$daten_raw[$i]);
		}
		//socket wieder schliessen
		socket_close($socket);
		
		// Werte anzeigen
		for ($i = 0; $i < $JavaWerte; ++$i)//vorwärts
		{
		
		      // Testbereich für weitere Variablen basierend auf ID-Liste
			  if (in_array($i, array_column($idListe, 'id'))) {
				// Variablen für vorhandene IDs erstellen oder aktualisieren
				$this->CreateOrUpdateVariable($i, $daten_raw[$i]);
			} else {
				// Variable löschen, da sie nicht mehr in der ID-Liste ist
				$this->DeleteVariableIfExists('WP_' . $java_dataset[$i]);
			}
		}

		// Funktion zur Erstellung von Variablen nach Name
		function CreateVariableByName($dummyModuleID, $name, $type, $ident, $profile, $position) {
			$vid = @IPS_GetObjectIDByIdent($ident, $dummyModuleID);
			if ($vid === false) {
				$vid = IPS_CreateVariable($type);
				IPS_SetParent($vid, $dummyModuleID);  // Setzen Sie das Dummy-Modul als Eltern-Objekt
				IPS_SetName($vid, $name);
				IPS_SetIdent($vid, $ident);
				IPS_SetInfo($vid, "");
				IPS_SetPosition($vid, $position);
		
				if ($profile !== "") {
					IPS_SetVariableCustomProfile($vid, $profile);
				}
			}
			return $vid;
		}
	
	}

	private function CreateOrUpdateVariable($id, $value)
{
    $minusTest = $value * 0.1;
    if ($minusTest > 429496000) {
        $value -= 4294967296;
        $value *= 0.1;
    } else {
        $value *= 0.1;
    }
    $value = round($value, 1);

    // Debug-Ausgabe
    $this->Log("Variable erstellen/aktualisieren für ID: " . $id);

    // Direkte Erstellung der Variable ohne Dummy-Modul-Bezug
    $varid = $this->RegisterVariableFloat('WP_' . $java_dataset[$id], $java_dataset[$id]);
    SetValueFloat($varid, $value);
}

	private function DeleteVariableIfExists($variableName)
{
    $variableID = @IPS_GetObjectIDByName($variableName, $this->InstanceID);
    if ($variableID !== false) {
        // Debug-Ausgabe
        $this->Log("Variable löschen: " . $variableName);

        // Variable löschen
        IPS_DeleteVariable($variableID);
    }

	}