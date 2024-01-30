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

        // Benötigte Varaiblen erstellen
        if (!IPS_VariableProfileExists("WPLUX.Sec")) {
			IPS_CreateVariableProfile("WPLUX.Sec", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.Sec", 0, 0, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Sec", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Sec", "", " sec"); //Präfix, Suffix
		}
        if (!IPS_VariableProfileExists("WPLUX.Imp")) {
			IPS_CreateVariableProfile("WPLUX.Imp", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.Imp", 0, 0, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Imp", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Imp", "", " impulse"); //Präfix, Suffix
		}
        if (!IPS_VariableProfileExists("WPLUX.Typ")) {
			IPS_CreateVariableProfile("WPLUX.Typ", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.Typ", 0, 0, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Typ", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Typ", "", ""); //Präfix, Suffix
		}
        if (!IPS_VariableProfileExists("WPLUX.Biv")) {
			IPS_CreateVariableProfile("WPLUX.Biv", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.Biv", 1, 3, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Biv", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Biv", "", ""); //Präfix, Suffix
            IPS_SetVariableProfileAssociation("WPLUX.Biv", 1, "ein Verdichter darf laufen", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Biv", 2, "zwei Verdichter dürfen laufen", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Biv", 3, "zusätzlicher Wärmeerzeuger darf mitlaufen", "", -1);
		}
        if (!IPS_VariableProfileExists("WPLUX.BZ")) {
			IPS_CreateVariableProfile("WPLUX.BZ", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.BZ", 0, 7, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.BZ", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.BZ", "", ""); //Präfix, Suffix
            IPS_SetVariableProfileAssociation("WPLUX.BZ", 0, "Heizen", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.BZ", 1, "Warmwasser", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.BZ", 2, "Schwimmbad / Photovoltaik", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.BZ", 3, "EVU", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.BZ", 4, "Abtauen", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.BZ", 5, "Keine Anforderung", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.BZ", 6, "Heizen ext. Energiequelle", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.BZ", 7, "Kühlbetrieb ", "", -1);
		}
        if (!IPS_VariableProfileExists("WPLUX.Off")) {
			IPS_CreateVariableProfile("WPLUX.Off", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.Off", 1, 9, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Off", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Off", "", ""); //Präfix, Suffix
            IPS_SetVariableProfileAssociation("WPLUX.Off", 1, "Wärmepumpe Störung", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Off", 2, "Anlagen Störung", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Off", 3, "Betriebsart Zweiter Wärmeerzeuger", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Off", 4, "EVU-Sperre", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Off", 5, "Lauftabtau (nur LW-Geräte)", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Off", 6, "Temperatur Einsatzgrenze maximal", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Off", 7, "Temperatur Einsatzgrenze minimal", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Off", 8, "Untere Einsatzgrenze", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Off", 9, "Keine Anforderung ", "", -1);
		}
        if (!IPS_VariableProfileExists("WPLUX.Comf")) {
			IPS_CreateVariableProfile("WPLUX.Comf", 0); //0 für Bool
			IPS_SetVariableProfileValues("WPLUX.Comf", 0, 1, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Comf", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Comf", "", ""); //Präfix, Suffix
            IPS_SetVariableProfileAssociation("WPLUX.Comf", 0, "nicht verbaut", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Comf", 1, "verbaut", "", -1);
		}
        if (!IPS_VariableProfileExists("WPLUX.Men1")) {
			IPS_CreateVariableProfile("WPLUX.Men1", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.Men1", 0, 7, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Men1", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Men1", "", ""); //Präfix, Suffix
            IPS_SetVariableProfileAssociation("WPLUX.Men1", 0, "Wärmepumpe läuft", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men1", 1, "Wärmepumpe steht", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men1", 2, "Wärmepumpe kommt", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men1", 3, "Fehlercode Speicherplatz 0", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men1", 4, "Abtauen", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men1", 5, "Warte auf LIN-Verbindung", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men1", 6, "Verdichter heizt auf", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men1", 7, "Pumpenvorlauf ", "", -1);
        }
        if (!IPS_VariableProfileExists("WPLUX.Men2")) {
			IPS_CreateVariableProfile("WPLUX.Men2", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.Men2", 0, 1, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Men2", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Men2", "", ""); //Präfix, Suffix
            IPS_SetVariableProfileAssociation("WPLUX.Men2", 0, "seit :", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men2", 1, "in : ", "", -1);
        }
        if (!IPS_VariableProfileExists("WPLUX.Men3")) {
			IPS_CreateVariableProfile("WPLUX.Men3", 1); //1 für Integer
			IPS_SetVariableProfileValues("WPLUX.Men3", 0, 17, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("WPLUX.Men3", 0); //Nachkommastellen
			IPS_SetVariableProfileText("WPLUX.Men3", "", ""); //Präfix, Suffix
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 0, "Heizbetrieb", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 1, "Keine Anforderung", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 2, "Netz-Einschaltverzögerung", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 3, "Schaltspielsperre", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 4, "Sperrzeit", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 5, "Brauchwasser", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 6, "Info Ausheizprogramm", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 7, "Abtauen", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 8, "Pumpenvorlauf", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 9, "Thermische Desinfektion", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 11, "Heizbetrieb", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 12, "Schwimmbad / Photovoltaik", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 13, "Heizen ext. Energiequelle", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 14, "Brauchwasser ext. Energiequelle", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 16, "Durchflussüberachung", "", -1);
            IPS_SetVariableProfileAssociation("WPLUX.Men3", 17, "Zweiter Wärmeerzeuger 1 Betrieb ", "", -1);
        }
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

        //socket wieder schließen
        socket_close($socket);

        // Werte anzeigen
        for ($i = 0; $i < $JavaWerte; ++$i) {
        if (in_array($i, array_column($idListe, 'id'))) {
        $value = $this->convertValueBasedOnID($daten_raw[$i], $i);

        // Debug senden
        $this->SendDebug("ID : Wert der Abfrage", "".$i." : ".$value."", 0);

        // Direkte Erstellung oder Aktualisierung der Variable mit Ident und Positionsnummer
        $ident = 'WP_' . $java_dataset[$i];
        $varid = $this->CreateOrUpdateVariable($ident, $value, $i);
        } else {
        // Variable löschen, da sie nicht mehr in der ID-Liste ist
        $this->DeleteVariableIfExists('WP_' . $java_dataset[$i]);
            }
        }
    }
                
    private function AssignVariableProfilesAndType($varid, $id)
    {
        // Hier erfolgt die Zuordnung des Variablenprofils und -typs basierend auf der 'id'
        switch (true) {

                case (($id >= 10 && $id <= 28) || $id == 122):
                    if ($varid) {
                        IPS_SetVariableCustomProfile($varid, '~Temperature');
                    }
                    return 2; // Float-Typ
                
                case ($id >= 29 && $id <= 55):
                    if ($varid) {
                        IPS_SetVariableCustomProfile($varid, '~Switch');
                    }
                    return 0; // Boolean-Typ

                case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 77) || $id == 120):
                    if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Sec');
                        }
                    return 1; // Integer
                
                case ($id == 57 || $id == 59):
                    if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Imp');
                        }
                    return 1; // Integer

                case ($id == 78):
                        if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Typ');
                        }
                        return 1; // Integer

                case ($id == 79):
                        if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Biv');
                        }
                        return 1; // Integer

                case ($id == 80):
                        if ($varid > 0) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.BZ');
                        }
                        return 1; // Integer

                case (($id >= 95 && $id <= 99) || ($id >= 111 && $id <= 115)):
                        if ($varid) {
                        IPS_SetVariableCustomProfile($varid, '~UnixTimestamp');
                        }
                        return 1; // Integer

                case ($id >= 106 && $id <= 110):
                        if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Off');
                        }
                        return 1; // Integer

                case ($id == 116 ):
                        if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Comf');
                        }
                        return 0; // Boolean-Typ

                case ($id == 117):
                        if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Men1');
                        }
                        return 1; // Integer
                        
                case ($id == 118):
                        if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Men2');
                        }
                        return 1; // Integer

                case ($id == 119):
                        if ($varid) {
                        IPS_SetVariableCustomProfile($varid, 'WPLUX.Men3');
                        }
                        return 1; // Integer
                /*
            case ($id == 29):
                    if ($varid > 0) {
                        IPS_SetVariableCustomProfile($varid, '~Switch');
                    }
                    return 0; // Boolean-Typ
                    */
            
            default:
                // Standardprofil, falls keine spezifische Zuordnung gefunden wird
                if ($varid > 0) {
                    IPS_SetVariableCustomProfile($varid, '');
                }
                return 1; // Standardmäßig Integer-Typ
        }
    }
    
    private function convertValueBasedOnID($value, $id)
    {
        // Hier erfolgt die Konvertierung des Werts basierend auf der 'id'
        switch ($id) {
        
        case ($id >= 10 && $id <= 28):
            return round($value * 0.1, 1);
        
        /*
        case ($id >= 29 && $id <= 55):
            return boolval($value);
        */    
        
        // Weitere Zuordnungen für andere 'id' hinzufügen
        default:
            return round($value * 1, 1); // Standardmäßig Konvertierung
        }
    }
            
    private function CreateOrUpdateVariable($ident, $value, $id)
    {
        $value = $this->convertValueBasedOnID($value, $id);

        // Überprüfen, ob die Variable bereits existiert
        $existingVarID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

        if ($existingVarID === false) {
            // Variable existiert nicht, also erstellen
            $varid = IPS_CreateVariable($this->AssignVariableProfilesAndType(null, $id));
            IPS_SetParent($varid, $this->InstanceID);
            IPS_SetIdent($varid, $ident);
            IPS_SetName($varid, $ident);
            SetValue($varid, $value);
            IPS_SetPosition($varid, $id);

            // Hier die Methode aufrufen, um das Profil zuzuweisen
            $this->AssignVariableProfilesAndType($varid, $id);
        } else {
            // Variable existiert, also aktualisieren
            $varid = $existingVarID;
            // Überprüfen, ob der Variablentyp stimmt
            if (IPS_GetVariable($varid)['VariableType'] != $this->AssignVariableProfilesAndType($varid, $id)) {
                // Variablentyp stimmt nicht überein, also Variable neu erstellen
                IPS_DeleteVariable($varid);
                $varid = IPS_CreateVariable($this->AssignVariableProfilesAndType(null, $id));
                IPS_SetParent($varid, $this->InstanceID);
                IPS_SetIdent($varid, $ident);
                IPS_SetName($varid, $ident);
                SetValue($varid, $value);
                IPS_SetPosition($varid, $id);
            } else {
                // Variablentyp stimmt überein, also nur Wert aktualisieren
                SetValue($varid, $value);
            }
        }
        return $varid;
    }

    private function DeleteVariableIfExists($ident)
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($variableID !== false) {
            // Debug-Ausgabe
            $this->SendDebug("Variable gelöscht", "$ident", 0);
                
            // Variable löschen
            IPS_DeleteVariable($variableID);
        }
    }
}