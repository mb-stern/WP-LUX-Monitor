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

        //Debug senden
        $this->SendDebug("Verbindungseinstellung im Config", "".$IpWwc.":".$WwcJavaPort."", 0);

        // Integriere Variabelbenennung aus den Java Daten
        require_once __DIR__ . '/java_daten.php';

        // Lese die ID-Liste
        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);

        // Socket verbinden
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $IpWwc, $WwcJavaPort);

        //Debug senden
        if (!$connect) {
            $error_code = socket_last_error();
            $this->SendDebug("Verbindung zum Socket fehlgeschlagen. Error:", "$error_code", 0);
            } else {
            $this->SendDebug("Verbindung zum Socket erfolgreich", "".$IpWwc.":".$WwcJavaPort."", 0);
        }

        // Daten holen
        $msg = pack('N*',3004);
        $send=socket_write($socket, $msg, 4); //3004 senden

        $msg = pack('N*',0);
        $send=socket_write($socket, $msg, 4); //0 senden

        socket_recv($socket,$Test,4,MSG_WAITALL);  // Lesen, sollte 3004 zurückkommen
        $Test = unpack('N*',$Test);

        socket_recv($socket,$Test,4,MSG_WAITALL); // Status
        $Test = unpack('N*',$Test);

        socket_recv($socket,$Test,4,MSG_WAITALL); // Länge der nachfolgenden Werte
        $Test = unpack('N*',$Test);

        $JavaWerte = implode($Test);

        for ($i = 0; $i < $JavaWerte; ++$i)//vorwärts
        {
        socket_recv($socket,$InBuff[$i],4,MSG_WAITALL);  // Lesen, sollte 3004 zurückkommen
        $daten_raw[$i] = implode(unpack('N*',$InBuff[$i]));
        }
        
        //socket wieder schliessen
        socket_close($socket);

        // Werte anzeigen
        for ($i = 0; $i < $JavaWerte; ++$i) {
            if (in_array($i, array_column($idListe, 'id'))) {
                $minusTest = $daten_raw[$i] * 0.1;
                if ($minusTest > 429496000) {
                    $daten_raw[$i] -= 4294967296;
                    $daten_raw[$i] *= 0.1;
                } else {
                    $daten_raw[$i] *= 0.1;
                }
                $daten_raw[$i] = round($daten_raw[$i], 1);
                
                // Debug senden
                $this->SendDebug("Gewählte ID für Abfrage", "$i", 0);

                // Direkte Erstellung der Variable mit Ident und Positionsnummer
                $ident = 'WP_' . $java_dataset[$i];
                $varid = $this->CreateOrUpdateVariable($ident, $daten_raw[$i], $i);

                // Zuordnung des Variablenprofils basierend auf der 'id'
                $this->AssignVariableProfiles($varid, $i);
            } else {
                // Variable löschen, da sie nicht mehr in der ID-Liste ist
                $this->DeleteVariableIfExists('WP_' . $java_dataset[$i]);
            }
        }
    }

	private function CreateOrUpdateVariable($ident, $value, $position)
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
        $this->SendDebug("Variabelwert aktualisiert", "$ident", 0);

        // Direkte Erstellung der Variable mit Ident
        $varid = $this->RegisterVariableFloat($ident, $ident);
        SetValue($varid, $value);

        // Position setzen
        IPS_SetPosition($varid, $position);

        return $varid;
    }

    private function AssignVariableProfiles($varid, $id)
{
    // Hier erfolgt die Zuordnung des Variablenprofils und Typs basierend auf der 'id'
    //0 für BOOLEAN
    //1 für INTEGER
    //2 für FLOAT
    //3 für STRING
    switch ($id) {
        case 10:
            IPS_SetVariableCustomProfile($varid, '~Temperature');
            IPS_SetVariable($varid, $this->RegisterVariableFloat($varid, 'Temperature', '~Temperature'));
            break;
        case 29:
            IPS_SetVariableCustomProfile($varid, '~Switch');
            IPS_SetVariable($varid, $this->RegisterVariableBoolean($varid, 'Switch', '~Switch'));
            break;
        // Weitere Zuordnungen für andere 'id' hinzufügen
        default:
            // Standardprofil und Typ, falls keine spezifische Zuordnung gefunden wird
            IPS_SetVariableCustomProfile($varid, '');
            IPS_SetVariable($varid, $this->RegisterVariableInteger($varid, 'Default', ''));
            break;
    }
}

	private function DeleteVariableIfExists($ident)
	{
		$variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
		if ($variableID !== false) {
		// Debug-Ausgabe
		$this->Log("Variable löschen: " . $ident);

		// Variable löschen
		IPS_DeleteVariable($variableID);
		}
	}
} 