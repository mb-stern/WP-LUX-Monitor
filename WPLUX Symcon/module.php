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
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '192.168.178.59');
        $this->RegisterPropertyInteger('Port', 8889);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        $this->RegisterTimer('UpdateTimer', 0, 'WPLUX_Update(' . $this->InstanceID . ');');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        $this->Update();
    }

    public function Update()
    {
        $IpWwc = "{$this->ReadPropertyString('IPAddress')}";
        $WwcJavaPort = "{$this->ReadPropertyInteger('Port')}";
        $SiteTitle = "WÄRMEPUMPE";

        require_once __DIR__ . '/../java_daten.php';

        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);

        $this->Log("ID-Liste: " . print_r($idListe, true));

        $sBuff = 0;
        $time1 = time();
        $filename = "test.tst";
        $JavaWerte = 0;
        $refreshtime = 5; // Sekunden

        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $IpWwc, $WwcJavaPort);

        if (!$connect) {
            $error_code = socket_last_error();
            exit("Socket connect failed with error code: $error_code\n");
        }

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

                $this->Log("Variable erstellen/aktualisieren für ID: " . $i);

                $ident = 'WP_' . $java_dataset[$i];
                $id = $idListe[$i]['id'];
                $varid = $this->CreateOrUpdateVariable($ident, $daten_raw[$i], $id);
            } else {
                $this->DeleteVariableIfExists('WP_' . $java_dataset[$i]);
            }
        }
    }

    private function CreateOrUpdateVariable($ident, $value, $id)
    {
        $this->Log("Variable erstellen/aktualisieren für Ident: " . $ident);

        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        
        if ($variableID === false) {
            // Variable existiert noch nicht, erstelle sie mit der angegebenen ID
            $variableID = IPS_CreateVariable(2); // 2 steht für Float
            IPS_SetParent($variableID, $this->InstanceID);
            IPS_SetIdent($variableID, $ident);
        }

        // Setze den Variablenwert
        SetValueFloat($variableID, $value);

        // Setze die Variable-ID aus der IDListe
        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);
        $idListeIndex = array_search($id, array_column($idListe, 'id'));
        
        if ($idListeIndex !== false) {
            $idListe[$idListeIndex]['variableID'] = $variableID;
            $this->WritePropertyString('IDListe', json_encode($idListe));
        }

        return $variableID;
    }

    private function DeleteVariableIfExists($ident)
    {
        $variableID = IPS_GetObjectIDByIdent($ident, $this->InstanceID);

        if ($variableID !== false) {
            $this->Log("Variable löschen: " . $ident);

            IPS_DeleteVariable($variableID);
        }
    }
}
