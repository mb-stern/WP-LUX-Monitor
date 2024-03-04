<?php

class WPLUX extends IPSModule
{
    private $updateTimer;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '0.0.0.0');
        $this->RegisterPropertyInteger('Port', 8889);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);
        
        $this->RegisterPropertyBoolean('HeizungVisible', false);
        $this->RegisterPropertyBoolean('KuehlungVisible', false);
        $this->RegisterPropertyBoolean('WarmwasserVisible', false);
        $this->RegisterPropertyBoolean('TempsetVisible', false);
        $this->RegisterPropertyBoolean('WWsetVisible', false);
        $this->RegisterPropertyFloat('kwin', 0);
        $this->RegisterPropertyFloat('kwhin', 0);
        $this->RegisterPropertyBoolean('HZ_TimerWeekVisible', false);
        $this->RegisterPropertyBoolean('HZ_TimerWeekendVisible', false);
        $this->RegisterPropertyBoolean('HZ_TimerDayVisible', false);
        $this->RegisterPropertyBoolean('BW_TimerWeekVisible', false);
        $this->RegisterPropertyBoolean('BW_TimerWeekendVisible', false);
        $this->RegisterPropertyBoolean('BW_TimerDayVisible', false);

        $this->RegisterAttributeFloat("start_value_out", 0);
        $this->RegisterAttributeFloat("start_kwh_in", 0);

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
        if ($ipAddress == '0.0.0.0') 
        {
            $this->SendDebug("Konfiguration", "IP-Adresse ist nicht konfiguriert", 0);   
            $this->LogMessage("IP-Adresse ist nicht konfiguriert", KL_ERROR);
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
        $wwsetVisible = $this->ReadPropertyBoolean('WWsetVisible');
        $copVisible = $this->ReadPropertyFloat('kwin');
        $jazVisible = $this->ReadPropertyFloat('kwhin');
        $hz_timerWeekVisible = $this->ReadPropertyBoolean('HZ_TimerWeekVisible');
        $hz_timerWeekendVisible = $this->ReadPropertyBoolean('HZ_TimerWeekendVisible');
        $hz_timerDayVisible = $this->ReadPropertyBoolean('HZ_TimerDayVisible');
        $bw_timerWeekVisible = $this->ReadPropertyBoolean('BW_TimerWeekVisible');
        $bw_timerWeekendVisible = $this->ReadPropertyBoolean('BW_TimerWeekendVisible');
        $bw_timerDayVisible = $this->ReadPropertyBoolean('BW_TimerDayVisible');

        // Steuervariablen erstellen und senden an die Funktion RequestAction
        if ($heizungVisible) 
        {
            $this->RegisterVariableInteger('Heizung', 'Modus Heizung', 'WPLUX.Wwhe', 0);
            $this->getParameter('Heizung');
            $Value = $this->GetValue('Heizung');
            $this->EnableAction('Heizung');
        } 
        else 
        {
            $this->UnregisterVariable('Heizung');
        }

        if ($warmwasserVisible) 
        {
            $this->RegisterVariableInteger('Warmwasser', 'Modus Warmwasser', 'WPLUX.Wwhe', 1);
            $this->getParameter('Warmwasser');
            $Value = $this->GetValue('Warmwasser');
            $this->EnableAction('Warmwasser');
        } 
        else 
        {
            $this->UnregisterVariable('Warmwasser');
        }

        if ($kuehlungVisible) 
        {
            $this->RegisterVariableInteger('Kuehlung', 'Modus Kühlung', 'WPLUX.Kue', 2);
            $this->getParameter('Kuehlung');
            $Value = $this->GetValue('Kuehlung');   
            $this->EnableAction('Kuehlung');
        } 
        else 
        {
            $this->UnregisterVariable('Kuehlung');
        }

        if ($tempsetVisible) 
        {
            $this->RegisterVariableFloat('Tempset', 'Temperaturkorrektur', 'WPLUX.Tset', 3);
            $this->getParameter('Tempset'); 
            $Value = $this->GetValue('Tempset'); 
            $this->EnableAction('Tempset');
        } 
        else 
        {
            $this->UnregisterVariable('Tempset');
        }

        if ($wwsetVisible) 
        {
            $this->RegisterVariableFloat('Wset', 'Warmwasser Soll', 'WPLUX.Wset', 4);
            $this->getParameter('Wset'); 
            $Value = $this->GetValue('Wset'); 
            $this->EnableAction('Wset');
        } 
        else 
        {
            $this->UnregisterVariable('Wset');
        }

        if ($copVisible !== 0 && IPS_VariableExists($copVisible)) 
        {
            $this->RegisterVariableFloat('copfaktor', 'COP-Faktor', '', 5);
        } 
        else 
        {
            $this->UnregisterVariable('copfaktor');
        }
        
        if ($jazVisible !== 0 && IPS_VariableExists($jazVisible)) 
        {
            $this->RegisterVariableFloat('jazfaktor', 'JAZ-Faktor', '', 6);
        } 
        else 
        {
            $this->UnregisterVariable('jazfaktor');
        }

        if ($hz_timerWeekVisible) //Variabelerstellung Timer Woche
        {
            $ids = [
                'set_223' => 'Woche von (1)', 'set_224' => 'Woche bis (1)',
                'set_225' => 'Woche von (2)', 'set_226' => 'Woche bis (2)',
                'set_227' => 'Woche von (3)', 'set_228' => 'Woche bis (3)'
            ];
            
            $position = -60; //ab dieser Position im Objektbaum einordnen

            foreach ($ids as $id => $name) 
            {
                $this->RegisterVariableInteger($id, $name, '~UnixTimestampTime', $position++);
                $this->getParameter($id);
                $this->GetValue($id);
                $this->EnableAction($id);
            }
        } 
        else 
        {
            $ids = 
            [
                'set_223', 'set_224', 'set_225', 'set_226', 'set_227', 'set_228'
            ];
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }

        if ($hz_timerWeekendVisible) //Variabelerstellung Timer Mo-Fr/Sa+So
        {
            $ids = [
                'set_229' => 'Mo-Fr von (1)', 'set_230' => 'Mo-Fr bis (1)', 'set_231' => 'Mo-Fr von (2)', 'set_232' => 'Mo-Fr bis (2)', 'set_233' => 'Mo-Fr von (3)', 'set_234' => 'Mo-Fr bis (3)',
                'set_235' => 'Sa+So von (1)', 'set_236' => 'Sa+So bis (1)', 'set_237' => 'Sa+So von (2)', 'set_238' => 'Sa+So bis (2)', 'set_239' => 'Sa+So von (3)', 'set_240' => 'Sa+So bis (3)'
            ];

            $position = -54; //ab dieser Position im Objektbaum einordnen
            
            foreach ($ids as $id => $name) 
            {
                $this->RegisterVariableInteger($id, $name, '~UnixTimestampTime', $position++);
                $this->getParameter($id);
                $this->GetValue($id);
                $this->EnableAction($id);
            }
        } 
        else 
        {
            $ids = 
            [
                'set_229', 'set_230', 'set_231', 'set_232', 'set_233', 'set_234', 'set_235', 'set_236', 'set_237', 'set_238', 'set_239', 'set_240'
            ];
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }

        if ($hz_timerDayVisible) //Variabelerstellung Timer Tage
        {
            $ids = [
                'set_241' => 'Sonntag von (1)', 'set_242' => 'Sonntag bis (1)', 'set_243' => 'Sonntag von (2)', 'set_244' => 'Sonntag bis (2)', 'set_245' => 'Sonntag von (3)', 'set_246' => 'Sonntag bis (3)',
                'set_247' => 'Montag von (1)', 'set_248' => 'Montag bis (1)', 'set_249' => 'Montag von (2)', 'set_250' => 'Montag bis (2)', 'set_251' => 'Montag von (3)', 'set_252' => 'Montag bis (3)',
                'set_253' => 'Dienstag von (1)', 'set_254' => 'Dienstag bis (1)', 'set_255' => 'Dienstag von (2)', 'set_256' => 'Dienstag bis (2)', 'set_257' => 'Dienstag von (3)', 'set_258' => 'Dienstag bis (3)',
                'set_259' => 'Mittwoch von (1)', 'set_260' => 'Mittwoch bis (1)', 'set_261' => 'Mittwoch von (2)', 'set_262' => 'Mittwoch bis (2)', 'set_263' => 'Mittwoch von (3)', 'set_264' => 'Mittwoch bis (3)',
                'set_265' => 'Donnerstag von (1)', 'set_266' => 'Donnerstag bis (1)', 'set_267' => 'Donnerstag von (2)', 'set_268' => 'Donnerstag bis (2)', 'set_269' => 'Donnerstag von (3)', 'set_270' => 'Donnerstag bis (3)',
                'set_271' => 'Freitag von (1)', 'set_272' => 'Freitag bis (1)', 'set_273' => 'Freitag von (2)', 'set_274' => 'Freitag bis (2)', 'set_275' => 'Freitag von (3)', 'set_276' => 'Freitag bis (3)',
                'set_277' => 'Samstag von (1)', 'set_278' => 'Samstag bis (1)', 'set_279' => 'Samstag von (2)', 'set_280' => 'Samstag bis (2)', 'set_281' => 'Samstag von (3)', 'set_282' => 'Samstag bis (3)'
            ];

            $position = -42; //ab dieser Position im Objektbaum einordnen
            
            foreach ($ids as $id => $name) 
            {
                $this->RegisterVariableInteger($id, $name, '~UnixTimestampTime', $position++);
                $this->getParameter($id);
                $this->GetValue($id);
                $this->EnableAction($id);
            }
        } 
        else 
        {
            $ids = 
            [
                'set_241', 'set_242', 'set_243', 'set_244', 'set_245', 'set_246', 'set_247', 'set_248', 'set_249', 'set_250', 'set_251', 'set_252', 'set_253', 'set_254', 'set_255', 'set_256', 'set_257', 'set_258', 'set_259', 'set_260', 'set_261', 'set_262','set_263', 'set_264',
                'set_265', 'set_266', 'set_267', 'set_268', 'set_269', 'set_270', 'set_271', 'set_272', 'set_273', 'set_274', 'set_275', 'set_276', 'set_277', 'set_278', 'set_279', 'set_280', 'set_281', 'set_282'
            ];
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }

        if ($bw_timerWeekVisible) //Variabelerstellung Timer Woche
        {
            $ids = 
            [
                'set_406' => 'Woche von (1)'
            ];
            
            $position = -120; //ab dieser Position im Objektbaum einordnen

            foreach ($ids as $id => $name) 
            {
                $this->RegisterVariableInteger($id, $name, '~UnixTimestampTime', $position++);
                $this->getParameter($id);
                $this->GetValue($id);
                $this->EnableAction($id);
            }
        } 
        else 
        {
            $ids = 
            [
                'set_406'
            ]
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }
    }

    public function RequestAction($Ident, $Value) 
    {
        $parameterMapping = [
            'Heizung', 'Kuehlung', 'Warmwasser', 'Wset', 'Tempset',
            'set_223', 'set_224', 'set_225', 'set_226', 'set_227', 'set_228', 'set_229', 'set_230',
            'set_231', 'set_232', 'set_233', 'set_234', 'set_235', 'set_236', 'set_237', 'set_238',
            'set_239', 'set_240', 'set_241', 'set_242', 'set_243', 'set_244', 'set_245', 'set_246',
            'set_247', 'set_248', 'set_249', 'set_250', 'set_251', 'set_252', 'set_253', 'set_254',
            'set_255', 'set_256', 'set_257', 'set_258', 'set_259', 'set_260', 'set_261', 'set_262',
            'set_263', 'set_264', 'set_265', 'set_266', 'set_267', 'set_268', 'set_269', 'set_270',
            'set_271', 'set_272', 'set_273', 'set_274', 'set_275', 'set_276', 'set_277', 'set_278',
            'set_279', 'set_280', 'set_281', 'set_282', 'set_406'
        ];

        if (in_array($Ident, $parameterMapping)) {
            $this->setParameter($Ident, $Value);
            $this->getParameter($Ident);
            $this->SendDebug("Parameter $Ident", "Folgender Wert wird an die Funktion setParameter gesendet: $Value", 0);
        }

        
    }

    
    public function Update()
    {
        //Verbindung zur Lux
        $IpWwc = "{$this->ReadPropertyString('IPAddress')}";
        $WwcJavaPort = "{$this->ReadPropertyInteger('Port')}";
        $SiteTitle = "WÄRMEPUMPE";

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
            $this->SendDebug("Socketverbindung", "Verbindung zum Socket fehlerhaft: ".$IpWwc.":".$WwcJavaPort." Fehler: ".$error_code."", 0);
            $this->LogMessage("Verbindung zum Socket fehlerhaft: ".$IpWwc.":".$WwcJavaPort." Fehler: ".$error_code."", KL_ERROR);
        } 
        else 
        {
            $this->SendDebug("Socketverbindung", "Verbindung zum Socket erfolgreich: ".$IpWwc.":".$WwcJavaPort."", 0);
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
            
            //Hier startet der Ablauf um Werte abzugreifen, welche ohne Auswahl eine ID zur Berechnung an die Funktion gesandt werden
            if ($i == 257) //Wärmeleistung an Funktion senden zur Berechnung des COP
            {
                $value = $this->convertValueBasedOnID($daten_raw[$i], $i);
                $this->calc_cop('cop', $value);
            }  

            if ($i == 154) //Wärmeleistung an Funktion senden zur Berechnung des JAZ
            {
                $value_out = $this->convertValueBasedOnID($daten_raw[$i], $i);
                $this->calc_jaz('jaz', $value_out);
            }
            
            //Hier startet der allgemeine Ablauf zum aktualiseren der Variablen nach Auswahl der ID's durch den Anwender
            if (in_array($i, array_column($idListe, 'id'))) 
            {
        
                // Werte umrechnen wenn nötig
                $value = $this->convertValueBasedOnID($daten_raw[$i], $i);

                // Direkte Erstellung oder Aktualisierung der Variable mit Ident und Positionsnummer
                $ident = $java_dataset[$i];
                $varid = $this->CreateOrUpdateVariable($ident, $value, $i);

                // Debug senden
                $this->SendDebug("Wert gesendet", "Der Wert: ".$daten_raw[$i]." der ID: ".$i." wurde erfasst, umgerechnet in: ".$value." und an die Funktion 'CreateOrUpdateVariable' gesandt", 0);

            }   
            
            else 
            {
            // Variable löschen, da sie nicht mehr in der ID-Liste ist
            $this->DeleteVariableIfExists($java_dataset[$i]);
            }
        }
    }
    
    private function convertValueBasedOnID($value, $id)
    {
        // Hier erfolgt die Konvertierung der Werte basierend auf der 'id'
        switch ($id) 
        {
            case (($id >= 10 && $id <= 14) || ($id >= 16 && $id <= 28) || $id == 122 || ($id >= 136 && $id <= 137) || ($id >= 142 && $id <= 144) || ($id >= 151 && $id <= 154) || ($id >= 175 && $id <= 179) ||$id == 183 || $id == 189 || ($id >= 194 && $id <= 200) || ($id >= 208 && $id <= 209) || ($id >= 227 && $id <= 229)):
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

            case (($id >= 67 && $id <= 77) || $id == 120 || $id == 123 || $id == 141|| $id == 158 || $id == 161): //Laufzeit umrechnen in Stunden und Minuten ausgeben
                $time = $value;
                $hours = floor($time / (60 * 60));
                $time -= $hours * (60 * 60);
                $minutes = floor($time / 60);
                $time -= $minutes * 60;
                $value = "{$hours}h {$minutes}m";
                return ($value); 
            
                case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 66)): //Laufzeit umrechnen in Stunden ausgeben
                $time = $value;
                $hours = floor($time / (60 * 60));
                $time -= $hours * (60 * 60);
                $value = $hours;
                return ($value);

            case ($id == 147 || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169) || ($id >= 180 && $id <= 181) || ($id >= 187 && $id <= 188) || ($id >= 210 && $id <= 211)):
                return round($value * 0.01, 1);

            case ($id == 257):
                return round($value * 0.001, 2);
            
            default:
                return round($value * 1, 1); // Standardmäßig Konvertierung
        }
    }
            
    private function CreateOrUpdateVariable($ident, $value, $id)
    {
        // Variable erstellen und Profil zuordnen
        switch ($id) 
        {
                case (($id >= 10 && $id <= 28) || $id == 122 || $id == 136 || $id == 137 || ($id >= 142 && $id <= 144) || ($id >= 175 && $id <= 177) || $id == 189 || ($id >= 194 && $id <= 195) || ($id >= 198 && $id <= 200) || ($id >= 227 && $id <= 229)):
                    $this->RegisterVariableFloat($ident, $ident, '~Temperature', $id);
                    break;

                case (($id >= 29 && $id <= 55) || ($id >= 138 && $id <= 140) || $id == 146 || ($id >= 166 && $id <= 167) || ($id >= 170 && $id <= 171) || $id == 182 || $id == 186 || ($id >= 212 && $id <= 216)):
                    $this->RegisterVariableBoolean($ident, $ident, '~Switch', $id);
                    break;    
    
                case (($id >= 67 && $id <= 77) || $id == 120 || $id == 123 || $id == 141|| $id == 158 || $id == 161):
                    $this->RegisterVariableString($ident, $ident, '', $id);
                    break;

                case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 66)):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Std', $id);
                    break;
                    
                case ($id == 57 || $id == 59):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Imp', $id);
                    break;
    
                case ($id == 78):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Typ', $id);
                    break;
    
                case ($id == 79):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Biv', $id);
                    break;
    
                case ($id == 80):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.BZ', $id);
                    break;
    
                case (($id >= 95 && $id <= 99) || ($id >= 111 && $id <= 115) || $id == 134) || ($id >= 222 && $id <= 226):
                    $this->RegisterVariableInteger($ident, $ident, '~UnixTimestamp', $id);
                    break;
    
                case (($id >= 106 && $id <= 110) || ($id >= 217 && $id <= 221)):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Off', $id);
                    break;
    
                case ($id == 116 || $id == 172 || $id == 174):
                    $this->RegisterVariableBoolean($ident, $ident, 'WPLUX.Comf', $id);
                    break;

                case ($id == 117):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Men1', $id);
                    break;
                            
                case ($id == 118):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Men2', $id);
                    break;

                case ($id == 119):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Men3', $id);
                    break;

                case ($id == 124):
                    $this->RegisterVariableBoolean($ident, $ident, 'WPLUX.Akt', $id);
                    break;

                case ($id == 147 || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169)):
                    $this->RegisterVariableFloat($ident, $ident, '~Volt', $id);
                    break;

                case ($id == 173):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.lh', $id);
                    break;
    
                case (($id >= 178 && $id <= 179) || ($id >= 196 && $id <= 197) || ($id >= 208 && $id <= 209)):
                    $this->RegisterVariableFloat($ident, $ident, '~Temperature.Difference', $id);
                    break;
    
                case (($id >= 180 && $id <= 181) || ($id >= 210 && $id <= 211)):
                    $this->RegisterVariableFloat($ident, $ident, 'WPLUX.Pres', $id);
                    break;
    
                case ($id == 183):
                    $this->RegisterVariableFloat($ident, $ident, '~Valve.F', $id);
                    break;
    
                case ($id == 184 || $id == 193  || $id == 231):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Fan', $id);
                    break;
    
                case (($id >= 151 && $id <= 154)|| ($id >= 187 && $id <= 188)):
                    $this->RegisterVariableFloat($ident, $ident, '~Electricity', $id);
                    break;
    
                case ($id == 191):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Bet', $id);
                    break;
    
                case ($id == 231):
                    $this->RegisterVariableFloat($ident, $ident, '~Hertz', $id);
                    break;
    
                case ($id == 257):
                    $this->RegisterVariableFloat($ident, $ident, '~Power', $id);
                    break;

                default:
                    // Standardprofil, falls keine spezifische Zuordnung gefunden wird
                    $this->RegisterVariableString($ident, $ident, '', $id);
                    break;
        }

        $this->SetValue($ident, $value);
        $this->SendDebug("Variable aktualisiert", "Variable erstellt/aktualisiert und Profil zugeordnet, ID: ".$id.", Name: ".$ident.", Wert: ".$value."", 0);
    }
    
    private function DeleteVariableIfExists($ident)
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($variableID !== false) 
        {
            // Variable löschen
            $this->UnregisterVariable($ident);
            
            // Debug-Ausgabe
            $this->SendDebug("Variable gelöscht", "Variable wurde gelöscht da die ID nicht mehr in der ID-Liste vorhanden ist - Variablen-ID: ".$variableID."  Name: ".$ident."", 0);       
        }
    }

    private function setParameter($type, $value) //Parameter setzen, 3002
{
    // IP-Adresse und Port aus den Konfigurationseinstellungen lesen
    $ipWwc = $this->ReadPropertyString('IPAddress');
    $wwcJavaPort = $this->ReadPropertyInteger('Port');

    // Verbindung zum Socket herstellen
    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
    $connect = socket_connect($socket, $ipWwc, $wwcJavaPort);

    // Daten senden
    $msg = pack('N*', 3002); // 3002 senden aktivieren
    socket_write($socket, $msg, 4);

    // Parameter je nach Typ festlegen
    $parameter = 0;

    switch ($type) 
    {
        case 'Tempset':
            $parameter = 1;
            if ($value >= -5 && $value <= 5) $value *= 10; // Wert für Temperaturkorrektur
            break;
        case 'Wset':
            $parameter = 2;
            if ($value >= 30 && $value <= 65) $value *= 10; // Wert für Warmwasserkorrektur
            break;
        case 'Heizung':
            $parameter = 3;
            break;
        case 'Warmwasser':
            $parameter = 4;
            break;
        case 'Kuehlung':
            $parameter = 108;
            $value = ($value == 0) ? 0 : 1; // Wert für Kühlung auf 0 oder 1 setzen
            break;
        
        default: //Hier werden die ganzen Timer gespeichert
            if (strpos($type, 'set_') === 0) 
            {
                $parameter = (int) substr($type, 4);
                if ($parameter >= 223 && $parameter <= 282 && $value >= -3600 && $value <= 82800) 
                {
                    $value += 3600; // Unix-Zeit korrigieren
                }
            }
            break;
    }

        // SetParameter senden
        $msg = pack('N*', $parameter);
        socket_write($socket, $msg, 4);

        // Wert senden
        $msg = pack('N*', $value);
        socket_write($socket, $msg, 4);

        // Daten vom Socket empfangen und verarbeiten
        socket_recv($socket, $test, 4, MSG_WAITALL);  // Lesen, sollte 3002 zurückkommen
        socket_recv($socket, $test, 4, MSG_WAITALL); // Lesen, sollte Status zurückkommen

        // Socket schließen
        socket_close($socket);

        // Debug senden
        $this->SendDebug("Socketverbindung", "Der Parameter: $parameter mit dem Wert: $value wurde an den Socket gesendet", 0);
    }

    private function getParameter($mode) //Parameter holen, 3003
    {
        $ipWwc = $this->ReadPropertyString('IPAddress');
        $wwcJavaPort = $this->ReadPropertyInteger('Port');

        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $ipWwc, $wwcJavaPort);

        $msg = pack('N*', 3003);
        socket_write($socket, $msg, 4);

        $msg = pack('N*', 0);
        socket_write($socket, $msg, 4);

        socket_recv($socket, $test, 4, MSG_WAITALL);
        socket_recv($socket, $test, 4, MSG_WAITALL);
        $test = unpack('N*', $test);
        $javaWerte = implode($test);

        for ($i = 0; $i < $javaWerte; ++$i) 
        {
            socket_recv($socket, $inBuff[$i], 4, MSG_WAITALL);
            $datenRaw[$i] = implode(unpack('N*', $inBuff[$i]));
        }

        socket_close($socket);

        switch ($mode) 
        {
            case 'Heizung':
            case 'Warmwasser':
            case 'Kuehlung':
            case 'Tempset':
            case 'Wset':
                $index = $mode == 'Tempset' ? 1 : ($mode == 'Wset' ? 2 : ($mode == 'Heizung' ? 3 : ($mode == 'Warmwasser' ? 4 : 108)));
                $value = $datenRaw[$index];
                if ($mode == 'Tempset') 
                {
                    $value *= 0.1;
                    if ($value > 429496000) 
                    {
                        $value -= 4294967296;
                        $value *= 0.1;
                    }
                } 
                elseif ($mode == 'Wset') 
                {
                    $value *= 0.1;
                }
                $this->SetValue($mode, $value);
                $this->SendDebug("Parameter $mode", "Wert des Parameters $mode: $value von der Lux geholt und in Variable gespeichert", 0);
                break;
                
            default: //Hier werden die ganzen Timer geholt
                if (strpos($mode, 'set_') === 0) 
                {
                    $index = (int) substr($mode, 4);
                    if ($index >= 223 && $index <= 282) 
                    {
                        $this->SetValue($mode, $datenRaw[$index] - 3600);
                    }
                }
                break;
        }
    }

    private function calc_cop($mode, $value) //COP berechnen
    {
        $copfaktorVariableID = @$this->GetIDForIdent('copfaktor');
        $copVisible = $this->ReadPropertyFloat('kwin');
        
        if ($mode == 'cop' && $copVisible !== 0 && IPS_VariableExists($copVisible) && $copfaktorVariableID !== false)
            {
                $kw_in = GetValue($this->ReadPropertyFloat('kwin'));
                $cop = $value / $kw_in;
                $this->SetValue('copfaktor', $cop);
                
                $this->SendDebug("COP-Faktor", "Faktor: ".$cop." wurde berechnet anhand der Eingangsleistung: ".$kw_in." und Wärmeleistung: ".$value."", 0);
            }
    }

    private function calc_jaz(string $mode, float $value_out) //JAZ berechnen
    {
        $jazVisible = $this->ReadPropertyFloat('kwhin');
        $jazfaktorVariableID = @$this->GetIDForIdent('jazfaktor');
    
        if ($mode == 'jaz' && $jazVisible !== 0 && IPS_VariableExists($jazVisible) && $jazfaktorVariableID !== false)
        {
            $kwh_in = GetValue($this->ReadPropertyFloat('kwhin'));
    
            $this->SendDebug("JAZ-Berechnung", "Berechnungsgrundlagen eingegangen: Verbrauch (zum Zeitpunkt des Reset): ".$this->ReadAttributeFloat('start_kwh_in')." kWh, Produktion (zum Zeitpunkt des Reset): ".$this->ReadAttributeFloat('start_value_out')." kWh, Verbrauchs (gesamt): ".$kwh_in." kWh, Produktion (gesamt): ".$value_out." kWh", 0);
            
            if ($this->ReadAttributeFloat('start_kwh_in') == 0 || $this->ReadAttributeFloat('start_value_out') == 0) //Erstmalige Synchronisatiin bei Startwert 0
            {
                $this->WriteAttributeFloat('start_kwh_in', $kwh_in);
                $this->WriteAttributeFloat('start_value_out', $value_out);
            
                $this->SendDebug("JAZ-Synch", "Die Variabeln wurden synchronisiert (sollte nur einmalig nach dem Reset passieren)", 0);
            }

            $kwh_in_Change = $kwh_in - $this->ReadAttributeFloat('start_kwh_in');
            $value_out_Change = $value_out - $this->ReadAttributeFloat('start_value_out');
    
            if ($kwh_in_Change != 0) // Überprüfen, ob der Wert von $kwh_in_Change nicht 0 ist, um eine Division durch 0 zu verhindern
            {
                $jaz = $value_out_Change / $kwh_in_Change;
                $this->SetValue('jazfaktor', $jaz);
                $this->SendDebug("JAZ-Faktor", "Faktor: ".$jaz." wurde berechnet anhand des Energieverbrauchs (seit Reset): ".$kwh_in_Change." kWh und der Energieproduktion (seit Reset): ".$value_out_Change." kWh", 0);
            }
            else 
            {
                $this->SetValue('jazfaktor', 0);
                $this->SendDebug("JAZ-Faktor", "JAZ-Faktor konnte noch nicht berechnet werden da sich der Wert der Energieversorgung noch nicht geändert hat seit dem Reset", 0);
            } 
        }
    }

    public function reset_jaz() //Startwerte der JAZ-Berechnung zurücksetzen
    {
        $this->WriteAttributeFloat('start_kwh_in', 0);
        $this->WriteAttributeFloat('start_value_out', 0);
        $this->SendDebug("JAZ-Reset", "Der Reset der Start-Werte zur JAZ-Berechnung wurde durchgeführt", 0);
    }
}