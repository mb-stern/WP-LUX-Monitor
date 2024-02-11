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

        $this->RegisterPropertyString('IPAddress', '192.168.178.0');
        $this->RegisterPropertyInteger('Port', 8889);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);
        
        $this->RegisterPropertyBoolean('HeizungVisible', false);
        $this->RegisterPropertyBoolean('KuehlungVisible', false);
        $this->RegisterPropertyBoolean('WarmwasserVisible', false);
        $this->RegisterPropertyBoolean('TempsetVisible', false);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'WPLUX_Update(' . $this->InstanceID . ');');  
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Variableprofile erstellen wenn nicht vorhanden
        require_once __DIR__ . '/variable_profile.php';

        // Timer für Aktualisierung aktualisieren
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);
    
        // Hole die IP-Adresse und andere Konfigurationseinstellungen
        $ipAddress = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');

        // Überprüfe, ob die IP-Adresse nicht die Muster-IP ist
        if ($ipAddress == '192.168.178.0') 
        {
            $this->SendDebug("Konfiguration", "Bitte konfigurieren Sie die IP-Adresse.", 0);   
        } 
        else 
        {
            // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
            $this->Update();
        }

        // Überprüfen, ob die Checkboxen im Konfigurationsformuler zum erstellen der Variablen aktiviert sind
        $heizungVisible = $this->ReadPropertyBoolean('HeizungVisible');
        $kuehlungVisible = $this->ReadPropertyBoolean('KuehlungVisible');
        $warmwasserVisible = $this->ReadPropertyBoolean('WarmwasserVisible');
        $tempsetVisible = $this->ReadPropertyBoolean('TempsetVisible');

        // Variablen erstellen und senden an die Funktion RequestAction
        if ($heizungVisible) 
        {
            $this->RegisterVariableInteger('HeizungVariable', 'Modus Heizung', 'WPLUX.Wwhe', 0);
            $this->getParameter('Heizung');
            $Value = $this->GetValue('HeizungVariable');
            $this->EnableAction('HeizungVariable');
        } 
        else 
        {
            $this->UnregisterVariable('HeizungVariable');
        }

        if ($warmwasserVisible) 
        {
            $this->RegisterVariableInteger('WarmwasserVariable', 'Modus Warmwasser', 'WPLUX.Wwhe', 1);
            $this->getParameter('Warmwasser');
            $Value = $this->GetValue('WarmwasserVariable');
            $this->EnableAction('WarmwasserVariable');
        } 
        else 
        {
            $this->UnregisterVariable('WarmwasserVariable');
        }

        if ($kuehlungVisible) 
        {
            $this->RegisterVariableFloat('KuehlungVariable', 'Modus Kühlung', 'WPLUX.Kue', 2);
            $this->getParameter('Kuehlung');
            $Value = $this->GetValue('KuehlungVariable');   
            $this->EnableAction('KuehlungVariable');;
        } 
        else 
        {
            $this->UnregisterVariable('KuehlungVariable');
        }
        if ($tempsetVisible) 
        {
            $this->RegisterVariableFloat('TempsetVariable', 'Temperaturanpassung', 'WPLUX.Tset', 3);
            $this->getParameter('Tempset'); 
            $Value = $this->GetValue('TempsetVariable'); 
            $this->EnableAction('TempsetVariable');;
        } 
        else 
        {
            $this->UnregisterVariable('TempsetVariable');
        }
    }

    public function RequestAction($Ident, $Value) 
    {

        // Überprüfe, ob der Wert der 'HeizungVariable' geändert hat und senden an die Funktion sendDataToSocket
        if ($Ident == 'HeizungVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Heizung', $Value);
            $this->SendDebug("Heizfunktion", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0);   
        }
    
        // Überprüfe, ob die Aktion von der 'KuehlungVariable' ausgelöst wurde
        if ($Ident == 'KuehlungVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Kuehlung', $Value);
            $this->SendDebug("Kühlfunktion", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0); 
        }
    
        // Überprüfe, ob die Aktion von der 'WarmwasserVariable' ausgelöst wurde
        if ($Ident == 'WarmwasserVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Warmwasser', $Value);
            $this->SendDebug("Warmwasserfunktion", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0);  
        }
        if ($Ident == 'TempsetVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Tempset', $Value);
            $this->SendDebug("Temperaturanpassung", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0);   
        }
    }
    
    public function Update()
    {
        //Verbindung zur Lux
        $IpWwc = "{$this->ReadPropertyString('IPAddress')}";
        $WwcJavaPort = "{$this->ReadPropertyInteger('Port')}";
        $SiteTitle = "WÄRMEPUMPE";

        //Debug senden
        $this->SendDebug("Verbindungseinstellung im Config", "".$IpWwc.":".$WwcJavaPort."", 0);

        // Namen der Variablen laden (3004 Berechnungen lesen)
        require_once __DIR__ . '/java_3004.php';

        // Lese die ID-Liste
        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);

        // Socket verbinden
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $IpWwc, $WwcJavaPort);

        //Debug senden
        if (!$connect) 
        {
            $error_code = socket_last_error();
            $this->SendDebug("Verbindung zum Socket fehlgeschlagen. Error:", "$error_code", 0);
        } 
        else 
        {
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

        socket_close($socket);

        for ($i = 0; $i < $JavaWerte; ++$i) 
        {
            if (in_array($i, array_column($idListe, 'id'))) 
            {
        
                // Werte umrechnen wenn nötig
                $value = $this->convertValueBasedOnID($daten_raw[$i], $i);

                // Debug senden
                $this->SendDebug("Wert empfangen", "Der Wert: ".$daten_raw[$i]." der ID: ".$i." wurde von der WP empfangen, umgerechnet in: ".$value." und in eine Variable ausgegeben", 0);

                // Direkte Erstellung oder Aktualisierung der Variable mit Ident und Positionsnummer
                $ident = $java_dataset[$i];
                $varid = $this->CreateOrUpdateVariable($ident, $value, $i);
            }   
            else 
            {
                // Variable löschen, da sie nicht mehr in der ID-Liste ist
                $this->DeleteVariableIfExists($java_dataset[$i]);
            }
        }
    }
                
    private function AssignVariableProfilesAndType($varid, $id)
    {
        // Hier erfolgt die Zuordnung des Variablenprofils und -typs basierend auf der 'id'
        switch (true) 
        {
            case (($id >= 10 && $id <= 28) || $id == 122 || $id == 136 || $id == 137 || ($id >= 142 && $id <= 144) || ($id >= 175 && $id <= 177) || $id == 189 || ($id >= 194 && $id <= 195) || ($id >= 198 && $id <= 200) || ($id >= 227 && $id <= 229)):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~Temperature');
                }
                return 2; // Float-Typ
                
            case (($id >= 29 && $id <= 55) || ($id >= 138 && $id <= 140) || $id == 146 || ($id >= 166 && $id <= 167) || ($id >= 170 && $id <= 171) || $id == 182 || $id == 186 || ($id >= 212 && $id <= 216)):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~Switch');
                }
                return 0; // Boolean-Typ

            case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 77) || $id == 120 || $id == 123 || $id == 141|| $id == 158 || $id == 161):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '');
                }
                return 3; // String
                
            case ($id == 57 || $id == 59):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Imp');
                }
                return 1; // Integer

            case ($id == 78):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Typ');
                }
                return 1; // Integer

            case ($id == 79):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Biv');
                }
                return 1; // Integer

            case ($id == 80):
                if ($varid > 0) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.BZ');
                }
                return 1; // Integer

            case (($id >= 95 && $id <= 99) || ($id >= 111 && $id <= 115) || $id == 134) || ($id >= 222 && $id <= 226):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~UnixTimestamp');
                }
                return 1; // Integer

            case (($id >= 106 && $id <= 110) || ($id >= 217 && $id <= 221)):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Off');
                }
                return 1; // Integer

            case ($id == 116 || $id == 172 || $id == 174):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Comf');
                }
                return 0; // Boolean-Typ

            case ($id == 117):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Men1');
                }
                return 1; // Integer
                        
            case ($id == 118):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Men2');
                }
                return 1; // Integer

            case ($id == 119):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Men3');
                }
                return 1; // Integer

            case ($id == 124):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Akt');
                }
                return 0; // Boolean-Typ

            case ($id == 147 || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169)):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~Volt');
                }
                return 2; // Float-Typ

            case ($id == 173):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.lh');
                }
                return 1; // Integer

            case (($id >= 178 && $id <= 179) || ($id >= 196 && $id <= 197) || ($id >= 208 && $id <= 209)):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~Temperature.Difference');
                }
                return 2; // Float-Typ

            case (($id >= 180 && $id <= 181) || ($id >= 210 && $id <= 211)):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Pres');
                }
                return 2; // Float-Typ

            case ($id == 183):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~Valve.F');
                }
                return 2; // Float-Typ

            case ($id == 184 || $id == 193  || $id == 231):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Fan');
                }
                return 1; // Integer

            case (($id >= 151 && $id <= 154)|| ($id >= 187 && $id <= 188)):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~Electricity');
                }
                return 2; // Float-Typ

            case ($id == 191):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, 'WPLUX.Bet');
                }
                return 1; // Integer

            case ($id == 231):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~Hertz');
                }
                return 2; // Float

            case ($id == 257):
                if ($varid) 
                {
                    IPS_SetVariableCustomProfile($varid, '~Power');
                }
                return 2; // Float-Typ
 
            default:
                // Standardprofil, falls keine spezifische Zuordnung gefunden wird
                if ($varid > 0) 
                {
                    IPS_SetVariableCustomProfile($varid, '');
                }
                return 1; // Standardmäßig Integer-Typ
        }
    }
    
    private function convertValueBasedOnID($value, $id)
    {
        // Hier erfolgt die Konvertierung des Werts basierend auf der 'id'
        switch ($id) 
        {
            case (($id >= 10 && $id <= 14) || ($id >= 16 && $id <= 28) || $id == 122 || ($id >= 136 && $id <= 137) || ($id >= 142 && $id <= 144) || ($id >= 175 && $id <= 179) ||$id == 183 || $id == 189 || ($id >= 194 && $id <= 200) || ($id >= 208 && $id <= 209) || ($id >= 227 && $id <= 229)):
                return round($value * 0.1, 1);
            
            case ($id == 15): //Aussentemperatur Minustest
                $minusTest = $value * 0.1;
                if ($minusTest > 429496000) 
                {
                    $value -= 4294967296;
                    $value *= 0.1; 
                } 
                else 
                {
                    $value *= 0.1; 
                }
                return round($value, 1); 

                case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 77) || $id == 120 || $id == 123 || $id == 141|| $id == 158 || $id == 161): //Korrektur Laufzeit und umrechnen in Stunden und Minuten
                $time = $value - 1;
                $hours = floor($time / (60 * 60));
                $time -= $hours * (60 * 60);
                $minutes = floor($time / 60);
                $time -= $minutes * 60;
                $value = "{$hours}h {$minutes}m";
                return ($value); 

            case ($id == 147 || ($id >= 151 && $id <= 154) || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169) || ($id >= 180 && $id <= 181) || ($id >= 187 && $id <= 188) || ($id >= 210 && $id <= 211)):
                return round($value * 0.01, 1);

            case ($id == 257):
                return round($value * 0.001, 1);
            
            default:
                return round($value * 1, 1); // Standardmäßig Konvertierung
        }
    }
            
    private function CreateOrUpdateVariable($ident, $value, $id)
    {
        // Überprüfen, ob die Variable bereits existiert
        $existingVarID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

        if ($existingVarID === false) 
        {
            // Variable existiert nicht, also erstellen
            $varid = IPS_CreateVariable($this->AssignVariableProfilesAndType(null, $id));
            IPS_SetParent($varid, $this->InstanceID);
            IPS_SetIdent($varid, $ident);
            IPS_SetName($varid, $ident);
            SetValue($varid, $value);
            IPS_SetPosition($varid, $id);

            //Debug senden
            $this->SendDebug("Variable erstellt", "Variable wurde erstellt da sie noch nicht existiert - ID: ".$id."  Variablen-ID: ".$varid."  Name: ".$ident."  Wert: ".$value."", 0);

            // Hier die Methode aufrufen, um das Profil zuzuweisen
            $this->AssignVariableProfilesAndType($varid, $id);
        } 
        else 
        {
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

            //Debug senden
            $this->SendDebug("Variable erneut erstellt", "Variabletyp stimmt nicht überein, daher Variable gelöscht und erneut erstellt - ID: ".$id.", Variablen-ID: ".$varid.", Name: ".$ident.", Wert: ".$value."", 0);

            } 
            else 
            {
                // Variablentyp stimmt überein, also nur Wert aktualisieren
                SetValue($varid, $value);

                //Debug senden
                $this->SendDebug("Variable aktualisiert", "Variablentyp stimmt überein, daher wird nur der Wert aktualisiert - ID: ".$id."  Variablen-ID: ".$varid."  Name: ".$ident."  Wert: ".$value."", 0);
            }
        }
        return $varid;
    }

    private function DeleteVariableIfExists($ident)
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($variableID !== false) 
        {
        
            // Debug-Ausgabe
            $this->SendDebug("Variable gelöscht", "Variable wurde gelöscht da die ID nicht mehr in der ID-Liste vorhanden ist - Variablen-ID: ".$variableID."  Name: ".$ident."", 0);
                
            // Variable löschen
            IPS_DeleteVariable($variableID);
        }
    }

    private function sendDataToSocket($type, $value)
    {
        // IP-Adresse und Port aus den Konfigurationseinstellungen lesen
        $ipWwc = $this->ReadPropertyString('IPAddress');
        $wwcJavaPort = $this->ReadPropertyInteger('Port');

        // Verbindung zum Socket herstellen
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $ipWwc, $wwcJavaPort);

        // Daten senden
        $msg = pack('N*', 3002); // 3002 senden aktivieren
        $send = socket_write($socket, $msg, 4);

        // Parameter je nach Typ festlegen
        switch ($type) {
            case 'Tempset':
                $parameter = 1;
                break;
            case 'Heizung':
                $parameter = 3;
                break;
            case 'Warmwasser':
                $parameter = 4;
                break;
            case 'Kuehlung':
                $parameter = 108;
                break;
            default:
                $parameter = 0;
                break;
        }

        // SetParameter senden
        $msg = pack('N*', $parameter);
        $send = socket_write($socket, $msg, 4);

        // Auswahl senden
        switch ($type) 
        {
            case 'Kuehlung':
                $value = ($value == 0) ? 0 : 1; // Wert für Kühlung auf 0 oder 1 setzen
                break;
            case 'Tempset':
                $value = 10 * ($value + 0.5); // Wert für Temperaturkorrektur
                break;
            default:
                // Fallback auf 0, wenn der Wert nicht innerhalb des erwarteten Bereichs liegt
                $value = ($value >= 0 && $value <= 4) ? $value : 0;
                break;
        }

        //Debug senden
        $this->SendDebug("Socketverbindung", "Der Parameter: ".$parameter." und der Wert: ".$value." wurde an den Socket gesendet", 0);

        $msg = pack('N*', $value); // Wert packen
        $send = socket_write($socket, $msg, 4); // Daten senden

        // Daten vom Socket empfangen und verarbeiten
        socket_recv($socket, $test, 4, MSG_WAITALL);  // Lesen, sollte 3002 zurückkommen
        $test = unpack('N*', $test);

        socket_recv($socket, $test, 4, MSG_WAITALL); // Lesen, sollte Status zurückkommen
        $test = unpack('N*', $test);

        // Socket schließen
        socket_close($socket);
    }

    private function getParameter($mode)
    {

        // IP-Adresse und Port aus den Konfigurationseinstellungen lesen
        $ipWwc = $this->ReadPropertyString('IPAddress');
        $wwcJavaPort = $this->ReadPropertyInteger('Port');

        // Verbindung zum Socket herstellen
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $ipWwc, $wwcJavaPort);

        // Daten holen
        $msg = pack('N*', 3003); // 3003 Daten holen
        $send = socket_write($socket, $msg, 4); //3003 senden

        $msg = pack('N*',0);
        $send=socket_write($socket, $msg, 4); //0 senden

        socket_recv($socket,$Test,4,MSG_WAITALL);  // Lesen, sollte 3003 zurückkommen
        $Test = unpack('N*',$Test);
        
        socket_recv($socket,$Test,4,MSG_WAITALL); // Länge der nachfolgenden Werte
        $Test = unpack('N*',$Test);
        
        $JavaWerte = implode($Test);

        for ($i = 0; $i < $JavaWerte; ++$i)
        {
            socket_recv($socket,$InBuff[$i],4,MSG_WAITALL);
            $daten_raw[$i] = implode(unpack('N*',$InBuff[$i]));
        }
        
        socket_close($socket);
        
        for ($i = 0; $i < $JavaWerte; ++$i)
        {
            if ($mode == 'Heizung' && $i == 3) // Betriebsart Heizung
            {
                $this->SetValue('HeizungVariable', $daten_raw[$i]);
                $this->SendDebug("Modus Heizung", "Einstellung Modus Heizung von der Lux geholt und in Variable gespeichert", 0);
            }
            elseif ($mode == 'Warmwasser' && $i == 4) // Betriebsart Warmwasser
            {
                $this->SetValue('WarmwasserVariable', $daten_raw[$i]);
                $this->SendDebug("Modus Warmwasser", "Einstellung Modus Warmwasser von der Lux geholt und in Variable gespeichert", 0);
            }
            elseif ($mode == 'Kuehlung' && $i == 108) // Betriebsart Kühlung
            {
                $this->SetValue('KuehlungVariable', $daten_raw[$i]);
                $this->SendDebug("Modus Kühlung", "Einstellung Modus Kühlung von der Lux geholt und in Variable gespeichert", 0);
            }
            elseif ($mode == 'Tempset' && $i == 1) // Temperaturanpassung
            {
                $this->SetValue('KuehlungVariable', $daten_raw[$i]*0.1);
                $this->SendDebug("Temperaturanpasung", "Wert der Temperaturanpassung von der Lux geholt und in Variable gespeichert", 0);
            }
        }
    }
}