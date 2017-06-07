<?
/**************************************************************
Fingerreader GT511C3
***************************************************************/

	class FingerprintReaderGT511 extends IPSModule	{
		public function Create() {
			//Never delete this line!
			parent::Create();
			//These lines are parsed on Symcon Startup or Instance creation
			//You cannot use variables here. Just static values.
			$this->RegisterPropertyBoolean("logmax",true);		
			$Instanz_ID = $this->InstanceID;
			IPS_LogMessage("FingerprintReader","Instanz erstellt - InstanzID(in Module)=$Instanz_ID");
			//
			$this->RegisterVariableString ("Firmwaredatum", "Firmwaredatum");
			$this->RegisterVariableBoolean ("Identify","Identify","","-10" );
			$Speicherplatz_ID=$this->RegisterVariableInteger ("Speicherplatz","Speicherplatz","","-5" );
			//IPS_SetParent(IPS_GetVariableIDByName(IPS_GetVariableIDByName("Speicherplatz",$this->InstanceID),IPS_GetVariableIDByName("Identify",$this->InstanceID)),IPS_GetVariableIDByName("Identify",$this->InstanceID));
			$this->RegisterVariableBoolean ("LED","LED","~Switch","-5" );		
			//erst nach Variablenerstellung				
			//
			$this->CreateScriptLED_Ein();
			$this->CreateScriptLED_Aus();
			$this->CreateScriptResetIdentify();
		}
		
		public function Destroy()	{
			//Never delete this line!
			parent::Destroy();
		}		

		public function ApplyChanges()	{
			//Never delete this line!
			parent::ApplyChanges();
			$this->Antwort="erstbefuellung";
		}
	
		public function ReceiveData($JSONString) { 						//Empfang DATEN - Beispiel innerhalb einer Geräte/Device Instanz - wird von PS aufgerufen
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);		
			// Empfangene Daten vom Gateway/Splitter
			$data = json_decode($JSONString);
			$ComBufferData = $this->GetBuffer("ComBuffer");
			$recString=$ComBufferData.bin2hex(utf8_decode($data->Buffer));
			if ($debug) IPS_LogMessage($Name,"ReceiveData: $recString");
			//
			if (stristr($recString, '55aa0100') || stristr($recString, 'aa0100') && !(stristr($recString, '5aa50100')))  {  //IPS-COM verschluckt manchmal erste Byte
				list($first,$last) = explode('aa0100',$recString);     //um sicher zweiten Teil zu bekommen
				if ($debug) IPS_LogMessage($Name,"ReceiveData: Response-Packet: first-string=$first   last-string=$last");
				$recString='aa0100'.$last;																//&& !(stristr($recString, '55aa0100'))
				$laenge=18;
				if ($debug) IPS_LogMessage($Name,"ReceiveData: 55aa0100 (Response-Packet) erkannt: $recString");
				if (strlen($recString) >= $laenge)	{
					//Response Paket zerlegen in Wörter
					$highbyte=substr($recString, 8, 2);
					if ($highbyte=="10" or 	$highbyte=="FF") {
						$this->SetBuffer("Answer","NOACK");
					}
					else { 
						$this->setBuffer("Answer","ACK");
					}
					$word1=$highbyte.substr($recString, 6, 2);
					$word2=substr($recString, 12, 2).substr($recString, 10, 2);
					$word3=substr($recString, 16, 2).substr($recString, 14, 2);
					if ($debug) IPS_LogMessage($Name,"ReceiveData: Response-Word: Word1: $word1 Word2: $word2 Word3: $word3 Antwort: ".$this->getBuffer("Answer"));
					$this -> ResponseParameterAuswertung ($word1,$highbyte);	
					// $recString leeren
					if ($debug) IPS_LogMessage($Name,"ReceiveData: Leere recString da Länge erreicht");
					$recString ="";
				}
				// Inhalt von $recString im Puffer der Instanz speichern
				$this->SetBuffer("ComBuffer",$recString);
				$ComBufferData = $this->GetBuffer("ComBuffer");
				if ($debug) IPS_LogMessage($Name,"ReceiveData: ComBufferData= $ComBufferData");
			}
			elseif (stristr($recString, '5aa50100')) {
				if ($debug) IPS_LogMessage($Name,"ReceiveData: Letzter Befehl: ".$this->getBuffer("Command").", 5AA50100 (DATA-Packet) erkannt");
				$Befehl=$this->getBuffer("Command");
				if ($Befehl=="Open") {
					list($first,$last) = explode ('a50100',$recString);     //um sicher zweiten Teil zu bekommen
					$recString='5aa50100'.$last;
					if (strlen($recString) >= 60) {  //
						if ($debug) IPS_LogMessage($Name,"ReceiveData: Data-Packet: first-string=$first   last-string=$last");
						$Firmware=substr($last,0,2).".".substr($last,2,2).".".substr($last,6,2).substr($last,4,2);						
						$Jahr=(int)(substr($last,6,2).substr($last,4,2));
						if (stristr($recString, '000000000')) {
							list($first,$SerNumber) = explode ('000000000',$recString);
							if ($Jahr>2000 && $Jahr<2030) {
								$this->setBuffer("Answer","ACK");
								$FirmwareID=IPS_GetVariableIDByName("Firmwaredatum",($this->InstanceID));
								SetValue ($FirmwareID,$Firmware);
								IPS_LogMessage($Name,"ReceiveData:  Seriennummer: $SerNumber,  Firmwaredatum: $Firmware");
							}
						}	
						else {
							IPS_LogMessage($Name,"ReceiveData: Firmwaredatum und Seriennummer nicht korrekt ausgelsen - bitte OPEN neu versuchen!");
							$this->setBuffer("Answer","NOACK");
						}	
						$recString ="";
					}	
				}
				if ($Befehl=="GetRawImage") {
					if (strlen($recString) >= 22) {
						list($first,$last) = explode ('A5 01 00',$recString);     //um sicher zweiten Teil zu bekommen
						return false;
						/*if ($debug) echo "first-string=$first   last-string=$last";
						$Firmware=substr($last,1,2).".".substr($last,4,2).".".substr($last,10,2).substr($last,7,2);		
						SetValue ($Antwort_ID,"Firmware ist $Firmware");
						$Jahr=(int)(substr($last,10,2).substr($last,7,2));
						if ($Jahr>2000 && $Jahr<2030) {
							$FirmwareID=IPS_GetVariableIDByName("Firmwaredatum",$FingerOrdner);
							SetValue ($FirmwareID,$Firmware);
						}*/
						base64_decode($recString);
					}	
				}
			}
			//else {
				//alle nicht identifizierbaren Pakete in Buffer schreiben
				$this->SetBuffer("ComBuffer",$recString);
			//}	
			return;
		}

		public function Enrollment() {									//complete Enrollment 
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);			
			$this->setBuffer("Command","EnrollStart");
			$this->setBuffer("Answer","Begin");
			IPS_LogMessage($Name,"Enrollment: EnrollStart gestartet - dieser Vorgang dauert einige Sekunden - Bitte Geduld!"); 
			//als erstes Anzahl belegte Speicherplätze ermitteln
			$erg=$this->GetEnrollCount();
			//als zweites freien Speicherplatz suchen
			for ($i =($erg+1); $i <= 199; $i++) {
				//$Speicherplatzh=$this->hexToStr(dechex($i));
				$erg=$this->CheckEnrolled($i);
				IPS_Sleep(400);
				if ($debug) IPS_LogMessage($Name,"Enrollment: Prüfe Speicherplatz: $i auf Verfügbarkeit");
				//echo "\nSpeicherplatz ".ascii2hex($Speicherplatz);
				if ($erg==true) {
					if ($debug) IPS_LogMessage($Name,"Enrollment: Speicherplatz: $i schon belegt");
				}
				else {
					if ($debug) IPS_LogMessage($Name,"Enrollment: Speicherplatz: $i noch frei");
					IPS_LogMessage($Name,"Enrollment: Speicherplatz erfolgreich gefunden - Step 1 (von 8) - Bitte Geduld!"); 
					break;
				}
			}			
			//Enrollment starten
			$erg=$this->EnrollStart ($i);	//Übergabe als Integer
			IPS_Sleep(400);
			if($erg==true) {
				IPS_LogMessage($Name,"Enrollment: Enrollstart erfolgreich - Step 2 (von 8) - Bitte Geduld!"); 
				$erg=$this-> CaptureFinger(true);
				IPS_Sleep(400);
			}
			else return false;
			if($erg==true) {
				IPS_LogMessage($Name,"Enrollment: CaptureFinger1 erfolgreich - Step 3 (von 8) - Bitte Geduld!"); 
				$erg=$this-> Enroll1(true);
				IPS_Sleep(400);
			}			
			else return false;
			if($erg==true) {
				IPS_LogMessage($Name,"Enrollment: Enroll1 erfolgreich - Step 4 (von 8) - Bitte Geduld!"); 
				$erg=$this-> CaptureFinger(true);
				IPS_Sleep(400);
			}			
			else return false;	
			if($erg==true) {
				IPS_LogMessage($Name,"Enrollment: CaptureFinger2 erfolgreich - Step 5 (von 8) - Bitte Geduld!"); 
				$erg=$this-> Enroll2(true);
				IPS_Sleep(400);
			}			
			else return false;
			if($erg==true) {
				IPS_LogMessage($Name,"Enrollment: Enroll2 erfolgreich - Step 6 (von 8) - Bitte Geduld!"); 
				$erg=$this-> CaptureFinger(true);
				IPS_Sleep(400);
			}			
			else return false;			
			if($erg==true) {
				IPS_LogMessage($Name,"Enrollment: CaptureFinger3 erfolgreich - Step 7 (von 8) - Bitte Geduld!"); 
				$erg=$this-> Enroll3(true);
				IPS_Sleep(400);
			}			
			else return false;
			if($erg==true) {
				IPS_LogMessage($Name,"Enrollment erfolgreich abeschlossen - Step 8  (von 8) - Geschafft!"); 
			}			
			else return false;			
			return $erg;
        }	
			
        public function LEDein() {										//LED ein
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			if ($debug) IPS_LogMessage($Name,"LEDein gestartet");
			$erg=$this->SetLED(true);
			if ($debug) IPS_LogMessage($Name,"LEDein beendet");
			return ($erg);			
        }

		public function LEDaus() {										//LED aus
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			if ($debug) IPS_LogMessage($Name,"LEDaus gestartet");
			$erg=$this->SetLED(false);
			if ($debug) IPS_LogMessage($Name,"LEDaus beendet");
			return ($erg);
        }

		public function SetLED(bool $status) {							//Control CMOS LED
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$Instanz_ID = $this->InstanceID;
			$this->setBuffer("Command","SetLED");
			$this->setBuffer("Answer","Begin");
			$Command=array("\x12","\x00");				//'CmosLed'    : 0x12,   # 0:    Off LED   Nonzero:  On LED
			$LED_ID=IPS_GetVariableIDByName("LED",$Instanz_ID);
			if ($status==true) {
				$Parameter=array("\x01","\x00","\x00","\x00");
				if ($debug) IPS_LogMessage($Name,"SetLED einschalten");
				SetValueBoolean($LED_ID,true);
			}
			else  {
				$Parameter=array("\x00","\x00","\x00","\x00");
				if ($debug) IPS_LogMessage($Name,"SetLED ausschalten");
				SetValueBoolean($LED_ID,false);
			}
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"SetLED",4,400,"ACK");
			$this->setBuffer("Answer","END SetLED");	
			return ($erg);
		}		
		
		public function GetEnrollCount () {								//Get enrolled fingerprint count
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);			
			$this->setBuffer("Command","GetEnrollCount");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"GetEnrollCount gestartet");		// Get enrolled fingerprint count
			$Command=array("\x20","\x00");										//0: not to get extra info Nonzero: to get extra info
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$this->senden ($sendestring,"GetEnrollCount",3,600,"ACK");
			if ($this->getBuffer("ANSWER") == "NOACK") {
				$erg = "Fehler bei Abruf - erneut versuchen!";
			}
			else $erg=$this->getBuffer("EnrollCount");
			if ($debug) IPS_LogMessage($Name,"GetEnrollCount beendet");
			return ($erg);
		}
		
		public function CheckEnrolled (int $Speicherplatz) {			//Check whether the specified ID is already enrolled
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$this->setBuffer("Command","CheckEnrolled");
			$this->setBuffer("Answer","Begin");
			$Speicherplatzh=$this->hexToStr(dechex($Speicherplatz));
			if ($debug) IPS_LogMessage($Name,"CheckEnrolled $Speicherplatz gestartet");                                          
			$Command=array("\x21","\x00");										//Command = CheckEnrolled  Parameter =  ID(0~199);
			$Parameter=array($Speicherplatzh,"\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			//senden ($sendestring,$functionname,$replys,$delay,$answer)
			$erg=$this->senden ($sendestring,"CheckEnrolled",0,800,"ACK");		//NOACK für Enrollment erforderlich?	
			if ($debug) IPS_LogMessage($Name,"CheckEnrolled $Speicherplatz beendet"); 
			return $erg;
		}		

		public function CaptureFinger (bool $enroll_quality) {			//Capture a fingerprint image(256x256) from the sensor
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);								// Capture a fingerprint image(256x256) from the sensor   
			$this->setBuffer("Command","CaptureFinger");
			$this->setBuffer("Answer","Begin");			
			$Command=array("\x60","\x00");										// need for enrollment
			if ($enroll_quality==true) {
				$Parameter=array("\x01","\x00","\x00","\x00");                   //Parameter =0: not best image, but fast Nonzero:best image, but slow
				if ($debug) IPS_LogMessage($Name,"CaptureFinger gestartet: quality high - but slow");  
				$time=1500;
			}
			else {
				$Parameter=array("\x00","\x00","\x00","\x00");                   //Parameter =0: not best image, but fast  Nonzero:best image, but slow
				if ($debug) IPS_LogMessage($Name,"CaptureFinger gestartet: quality low - but quickly"); 
				$time=300;
			}
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"CaptureFinger",3,$time,"ACK");
			if ($debug) IPS_LogMessage($Name,"CaptureFinger beendet"); 
			return $erg;
		}		

		public function Identify () {									//include CaptureFinger and OnlyIdentify
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$this->setBuffer("Command","Identify");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"Identify gestartet");         // 1:N Identification of the capture fingerprint image with the database
			$erg=$this->CaptureFinger(false);
			IPS_Sleep(200);
			if($erg==true) {
				IPS_LogMessage($Name,"Identify: CaptureFinger erfolgreich - Step 1 (von 2)"); 
				$erg=$this-> OnlyIdentify();
			}
			else return (false);
			if ($debug) IPS_LogMessage($Name,"Identify beendet"); 
			return $erg;
		}
		
		public function IsFingerPress () {                              //Check if a finger is placed on the sensor
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$this->setBuffer("Command","IsFingerPress");
			$this->setBuffer("Answer","Begin");
			$Instanz_ID = $this->InstanceID;
			if ($debug) IPS_LogMessage($Name,"IsFingerPress gestartet");
			$Command=array("\x26","\x00");										//Response = Ack: Parameter = 0: finger is pressed Parameter = nonzero: finger is not pressed
			$Parameter=array("\x01","\x00","\x00","\x00");                      //This command is used while enrollment, the host waits to take off the finger per enrollment stage
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"IsFingerPress",3,600,"ACK");
			if ($debug) IPS_LogMessage($Name,"IsFingerPress beendet"); 			
			return ($erg);
		}

		public function DeleteAll () {						  			//Delete all fingerprints from the database
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);	
			$this->setBuffer("Command","DeleteAll");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"DeleteAll gestartet");
  			$Command=array("\x41","\x00");										//
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"DeleteAll",0,600,"ACK");
			If ($erg==true) IPS_LogMessage($Name,"Letzter Befehl: ".$this->getBuffer("Command").", Alle Speicherplätze gelöscht"); 
			if ($debug) IPS_LogMessage($Name,"DeleteAll beendet");
			return $erg;
		}
		
		public function DeleteID (int $Speicherplatz) {					//Delete the fingerprint with the specified ID
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$this->setBuffer("Command","DeleteID");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"DeleteID $Speicherplatz gestartet");
			$Speicherplatzh=$this->hexToStr(dechex($Speicherplatz));
			$Command=array("\x40","\x00");										//
			$Parameter=array($Speicherplatzh,"\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"DeleteID",2,600,"ACK");
			If ($erg==true) IPS_LogMessage($Name,"Letzter Befehl: ".$this->getBuffer("Command").", Speicherplatz: $Speicherplatz gelöscht"); 
			if ($debug) IPS_LogMessage($Name,"DeleteID $Speicherplatz beendet");
			return $erg;
		}

		public function  Open (bool $info) {							//Initialization - GetData (Firmware ...)
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);				
			$this->setBuffer("Command","Open");
			$this->setBuffer("Answer","Begin");
			if ($info==true) { 
				$Parameter=array("\x01","\x00","\x00","\x00");			//0: not to get extra info Nonzero: to get extra info4
				if ($debug) IPS_LogMessage($Name,"Open gestartet - Infos angefordert"); 
			}
			else {
				$Parameter=array("\x00","\x00","\x00","\x00");			//0: not to get extra info Nonzero: to get extra info4
				if ($debug) IPS_LogMessage($Name,"Open gestartet - Keine Infos angefordert");	
			}
			$Command=array("\x01","\x00");                          
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"Open",4,1500,"ACK");
			if ($debug) IPS_LogMessage($Name,"Open beendet");			
			return $erg;
		}
		
		public function  Close () { 									//Termination
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$this->setBuffer("Command","Close");			//funktion sinnlos
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"Close gestartet"); 
			$Command=array("\x02","\x00");										//0: not to get extra info Nonzero: to get extra info
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"Close",0,200,"ACK");
			if ($debug) IPS_LogMessage($Name,"Close beendet"); 
			return $erg;
		}

		public function GetImage () {									//derzeit nicht implementiert - kein Anwendungsfall
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$this->setBuffer("Command","GetImage");			
			$this->setBuffer("Answer","Begin");			
			if ($debug) IPS_LogMessage($Name,"GetImage gestartet");       			// Download the captured fingerprint image (256x256)
			$Command=array("\x62","\x00");										//
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=buildstring ($Parameter,$Command);
			$erg=senden ($sendestring,"GetImage",3,1700,"ACK");
			if ($debug) IPS_LogMessage($Name,"GetImage beendet");	
			return $erg;
		}		

		public function GetRAWImage () {								//derzeit nicht implementiert - kein Anwendungsfall
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$this->setBuffer("Command","GetRAWImage");			
			$this->setBuffer("Answer","Begin");			
			if ($debug) IPS_LogMessage($Name,"GetRAWImage gestartet");       			// Download the captured fingerprint image (256x256)
			$Command=array("\x63","\x00");										//
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=buildstring ($Parameter,$Command);
			$erg=senden ($sendestring,"GetRAWImage",3,1900,"ACK");
			if ($debug) IPS_LogMessage($Name,"GetRAWImage beendet");	
			return $erg;
		}
		
		/*	//https://www.sparkfun.com/products/1179 //[Objekt #1179-2 existiert nicht]/.html /
		OFFSET 	ITEM 		TYPE 	DESCRIPTION
		0		0x55		BYTE	Command start code1
		1		0xAA		BYTE	Command start code2
		2		Device ID   WORD	Device ID: default is 0x0001, always fixed
		4		Parameter	DWORD	Input parameter
		8		Command		WORD	Command code
		10		Check Sum	WORD	Check Sum (byte addition) OFFSET[0]+…+OFFSET[9]=Check Sum


		Response Packet (Acknowledge)	
		OFFSET	ITEM		TYPE	DESCRIPTION
		0		0x55		BYTE	Response start code1
		1		0xAA		BYTE	Response start code2
		2		Device ID   WORD	Device ID: default is 0x0001, always fixed
		4       Parameter	DWORD	Response == 0x30: (ACK) Output Parameter Response == 0x31: (NACK) Error code
		8     	Response	WORD	0x30: Acknowledge (ACK). 0x31: Non-acknowledge (NACK).
		10   	Check Sum	WORD	Check Sum (byte addition) OFFSET[0]+…+OFFSET[9]=Check Sum
		*/
		
		protected function ResponseParameterAuswertung ($word1,$highbyte) {	//Auswertung der Rückmeldungen 
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$Antwort=$this->getBuffer("Answer");
			$Befehl=$this->getBuffer("Command");
			if ($debug) IPS_LogMessage($Name,"ResponseAuswertung: Letzter Befehl: $Befehl, Letzte Antwort: $Antwort");
			if ($Antwort == "NOACK") {
				$error_codes = array (
										'0000' =>  	'NO_ERROR',    					//Default value. no error
										'1001' =>  	'NACK_TIMEOUT',      			//Obsolete, capture timeout
										'1002' =>  	'NACK_INVALID_BAUDRATE',  		//Obsolete, Invalid serial baud rate
										'1003' =>  	'NACK_INVALID_POS',     		//The specified ID is not between 0~199
										'1004' =>  	'NACK_IS_NOT_USED',     		//The specified ID is not used
										'1005' =>  	'NACK_IS_ALREADY_USED',      	//The specified ID is already used
										'1006' =>  	'NACK_COMM_ERR',     			//Communication Error
										'1007' =>  	'NACK_VERIFY_FAILED',    		//1:1 Verification Failure
										'1008' =>  	'NACK_IDENTIFY_FAILED',       	//1:N Identification Failure
										'1009' =>   'NACK_DB_IS_FULL',   			//The database is full
										'100a' =>  	'NACK_DB_IS_EMPTY',    			//The database is empty
										'100b' =>  	'NACK_TURN_ERR',   				//Obsolete, Invalid order of the enrollment (The order was not as, EnrollStart -> Enroll1 -> Enroll2 -> Enroll3)
										'100c' =>  	'NACK_BAD_FINGER',     			//Too bad fingerprint
										'100d' => 	'NACK_ENROLL_FAILED',		    //Enrollment Failure
										'100e' => 	'NACK_IS_NOT_SUPPORTED',      	//The specified command is not supported
										'100f' =>	'NACK_DEV_ERR',               	//Device Error, especially if Crypto-Chip is trouble
										'1010' => 	'NACK_CAPTURE_CANCELED',     	//Obsolete, The capturing is canceled
										'1011' => 	'NACK_INVALID_PARAM',      		//Invalid parameter
										'1012' =>   'NACK_FINGER_IS_NOT_PRESSED',   //Finger is not pressed
										'ffff' =>	'INVALID',      				//Used when parsing fails    		
				) ;
				$ErrorText=$error_codes[$word1];
				if ($debug) IPS_LogMessage($Name,"ResponseAuswertung: NOACK: $ErrorText $word1");
			}
			else {
				if ($Befehl == "OnlyIdentify") {
					IPS_LogMessage($Name,"Identify erfolgreich - Fingerabdruck erkannt - Speicherplatz: ".(hexdec($word1)-48));  //48 nichts in Doku enthalten
					$Identify_ID=IPS_GetVariableIDByName("Identify",$this->InstanceID);
					$Speicherplatz_ID=IPS_GetVariableIDByName("Speicherplatz",$Identify_ID); 	
					SetValueInteger($Speicherplatz_ID,(hexdec($word1)-48));
				}
				elseif ($Befehl == "GetEnrollCount") {
					IPS_LogMessage($Name,"GetEnrollCount erfolgreich - belegte Speicherplätze: ".hexdec($word1));
					$this->SetBuffer("EnrollCount",hexdec($word1));
				}
				elseif ($Befehl == "IsFingerPress") {
					if ($word1 == '1012') {
						IPS_LogMessage($Name,"IsFingerPress erfolgreich - Finger not pressed");
						$this->SetBuffer("Answer","ACK");			//NOACK für IsFingerPress okay - Finger not pressed
					}
					if ($word1 == '0000') {
						IPS_LogMessage($Name,"IsFingerPress erfolgreich - Finger pressed");
					}					
				}
				elseif ($Befehl == "CheckEnrolled") {
					if ($word1 == '1004') {
						IPS_LogMessage($Name,"CheckEnrolled erfolgreich - ID ist noch frei");
						$this->SetBuffer("Answer","ACK");			//NOACK für Checkerolled okay - ID noch frei
					}
					else {
						IPS_LogMessage($Name,"CheckEnrolled Fehler - $word1");
					}					
				}
				elseif ($Befehl == "DeleteID") {
					IPS_LogMessage($Name,"DeleteID erfolgreich - : ".hexdec($word1));
				}
				elseif ($Befehl == "DeleteAll") {
					IPS_LogMessage($Name,"DeleteAll erfolgreich - : ".hexdec($word1));
				}
				elseif (($Befehl == "Enroll1") || ($Befehl == "Enroll2") || ($Befehl == "Enroll3")) {
					$Speicherplatz=(int) hexdec($word1);
					if ($Speicherplatz>0 && $Speicherplatz<200) {
						IPS_LogMessage($Name,"Fingerprint schon eingespeichert - : ".hexdec($word1));
					}	
				}
			}
			return;
		}
		
		protected function senden (string $sendestring,string $functionname,int $replys,int $delay,string $answer) {
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);
			$ErrorCount=0;
			$COM_ID = $this -> GetParent();
			if ($COM_ID==false) {
				echo "Übergeordnete Instanz (SerialPort) muss verbunden sein!";
				IPS_LogMessage($Name,"Übergeordnete Instanz (SerialPort) muss verbunden sein!");
				return false;
			}	
			$Antwort=$this->GetBuffer("Answer");
			$Befehl=$this->GetBuffer("Command");
			if ($debug) IPS_LogMessage($Name,"Senden: ".$this->ascii2hex($sendestring));
			while ($Antwort!=$answer) {
				//SPRT_SendText($COM_ID, $sendestring);  //über seriellen Port direkt //sollte nicht verwandt werden (Probleme beim Test unter Windows)
				$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => utf8_encode($sendestring))));
				IPS_Sleep($delay);
				$Antwort=$this->GetBuffer("Answer");
				//
				if ($debug) IPS_LogMessage($Name,"Senden: Letzter Befehl: $Befehl, Letzte Antwort: ".$this->GetBuffer("Answer"));
				If ($Antwort!=$answer) 	{
					if ($debug) IPS_LogMessage($Name,"$functionname- Fehler - kein: $answer erhalten - Starte COM-SS neu!");
					IPS_SetProperty($COM_ID,"Open",true);			//serielle Schnittstelle verschluckt sich - IPS-Problem?
					IPS_Sleep($delay);
					//Nachfolgende Zeile Probleme unter Windows ??? 
					IPS_ApplyChanges($COM_ID);						//serielle Schnittstelle verschluckt sich - IPS-Problem?
					$ErrorCount++;
					if ($debug) IPS_LogMessage($Name,"$functionname- Fehler - kein: $answer erhalten");
					If ($ErrorCount>$replys) {    //muss mind. 1 sein
						IPS_LogMessage($Name,"Senden: $functionname - Abbruch kein: $answer erhalten (".$_IPS['SELF'].")");
						return false;
					}
				}
				else {
					IPS_LogMessage($Name,"Senden: $functionname - erfolgreich: $answer erhalten");
					return true;
				}
			}
			return;
		}		
		protected function OnlyIdentify () {							//only Identify Command - for real identify needs Capture
			$debug=$this->ReadPropertyBoolean("logmax");				
			$Name=IPS_GetName($this->InstanceID);						//1:N Identification of the capture fingerprint image with the database
			$this->setBuffer("Command","OnlyIdentify");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"OnlyIdentify gestartet");	
			$Command=array("\x51","\x00");									
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"OnlyIdentify",4,500,"ACK");
			if ($erg) {
				$Identify_ID=IPS_GetVariableIDByName("Identify",$this->InstanceID); 
				SetValueBoolean($Identify_ID,true);
				if ($debug) IPS_LogMessage($Name,"Setze Variable Identify ($Identify_ID) auf true"); 
			}
			if ($debug) IPS_LogMessage($Name,"OnlyIdentify beendet"); 
			return $erg;
		}	

		protected function EnrollStart ($Speicherplatz) {				
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);			
			$this->setBuffer("Command","EnrollStart");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"EnrollStart gestartet");        // Command = EnrollStart Parameter = ID(0~199) If ID == -1, then “Enrollment without saving” will be stated.
			$Command=array("\x22","\x00");										//
			$Parameter=array($Speicherplatz,"\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"EnrollStart",3,600,"ACK");
			if ($debug) IPS_LogMessage($Name,"EnrollStart beendet");
			return $erg;
		}		

		protected function Enroll1 () {
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);			
			$this->setBuffer("Command","Enroll1");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"Enroll1 gestartet");       	// Make 1st template for an enrollment
			$Command=array("\x23","\x00");										//
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"Enroll1",4,1500,"ACK");
			if ($debug) IPS_LogMessage($Name,"Enroll1 beendet");	
			return $erg;
		}	
		
		protected function Enroll2 () {
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);			
			$this->setBuffer("Command","Enroll2");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"Enroll2 gestartet");       	// Make 2st template for an enrollment
			$Command=array("\x24","\x00");										//
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"Enroll2",4,1500,"ACK");
			if ($debug) IPS_LogMessage($Name,"Enroll2 beendet");	
			return $erg;
		}	
		
		protected function Enroll3 () {
			$debug=$this->ReadPropertyBoolean("logmax");
			$Name=IPS_GetName($this->InstanceID);			
			$this->setBuffer("Command","Enroll3");
			$this->setBuffer("Answer","Begin");
			if ($debug) IPS_LogMessage($Name,"Enroll3 gestartet");       	// Make 2st template for an enrollment
			$Command=array("\x25","\x00");										//
			$Parameter=array("\x00","\x00","\x00","\x00");
			$sendestring=$this->buildstring ($Parameter,$Command);
			$erg=$this->senden ($sendestring,"Enroll3",4,1500,"ACK");
			if ($debug) IPS_LogMessage($Name,"Enroll3 beendet");	
			return $erg;
		}				
		
		protected function buildstring ($Parameter,$Command)   {		//erstellt Sendestring
			/*StartString
			COMMAND_START_CODE_1 = 0x55;    # Static byte to mark the beginning of a command packet    -    never changes
			COMMAND_START_CODE_2 = 0xAA;    # Static byte to mark the beginning of a command packet    -    never changes
			COMMAND_DEVICE_ID_1  = 0x01;    # Device ID Byte 1 (lesser byte)                           -    theoretically never changes
			COMMAND_DEVICE_ID_2  = 0x00;    # Device ID Byte 2 (greater byte)                          -    theoretically never changes
			*/
			$StartString=array("\x55","\xAA","\x01","\x00");
			$string=implode($StartString);  //         //in hex
			//print_r($Command);
			//print_r($Parameter);
			$string.=implode($Parameter);
			$string.=implode($Command);
			//if ($debug) echo "\nString in Buildstring1= $string";
			$checksum=$this->checksum($string);
			//$string.=($checksum);
			$string.=implode($checksum);
			return 	$string;
		}
		
		protected function checksum ($Msg) {							//berechnet Checksumme (nur in Senderichtung)
			//http://easyonlineconverter.com/converters/checksum_converter.html
			//http://binaer-dezimal-hexadezimal-umrechner.miniwebapps.de/
			$ChkSum = 0;                         	// Checksumme initialisieren
			$Msg=($Msg);
			//if ($debug) echo "\nMsg: $Msg";
			for($i=0; $i<strlen($Msg); $i++)  {   	// alle Bytes aufsummieren
				//$ChkSum += ord($Msg[$i]);
				$HighByte=ord($Msg[$i]);
				$LowByte=ord($Msg[$i+1]);
				$ChkSum += $HighByte +$LowByte;
				//if ($debug) echo"\nCheckSumByte=	$ChkSum";
				//if ($debug) echo "\nTeilbyte: $i: ".ord($Msg[$i])."\t  CHKSUM=$ChkSum\tdechex=".dechex($ChkSum)."  \t".decbin($ChkSum)."  \t".utf8_decode($ChkSum)."\t".(dechex((~$ChkSum)+1));
				$i++;
			}
			//
			$lb=(round($ChkSum/256,0));
			$hb=($ChkSum%256);
			//if ($debug) echo "\n\nLowByte0:  $lb";
			//if ($debug) echo "\tHighByte0: $hb";
			$lb=dechex($lb);
			$hb=dechex($hb);
			//if ($debug) echo "\n\nLowByte0x:  $lb";
			//if ($debug) echo "\tHighByte0x: $hb";
			$hb=$this->hexToStr($hb);
			$lb=$this->hexToStr($lb);
			$CheckWord=(array($hb,$lb));
			//if ($debug) echo "\nLowByte2:  ".ascii2hex($lb);
			//if ($debug) echo "\tHighByte2: ".ascii2hex($hb);
			//print_r($CheckWord);
			return $CheckWord;
		}		

		protected function getParent() { 								//ermittelt übergeordnete Instanz - @return int|bool InstanzID des Parent, false wenn kein Parent vorhanden.								
			$instance = IPS_GetInstance($this->InstanceID);
			$Name=IPS_GetName($this->InstanceID);
			return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;   //Ermittlung der COM_ID
		}		

		protected function ascii2hex($ascii) {
		  $hex = '';
		  for ($i = 0; $i < strlen($ascii); $i++) {
			$byte = strtoupper(dechex(ord($ascii{$i})));
			$byte = str_repeat('0', 2 - strlen($byte)).$byte;
			$hex.=$byte." ";
		  }
		  return $hex;
		}

		protected function hexToStr($hex){ 
			$string='';
			//if ($debug) echo "\nLänge=".strlen($hex)."  $hex ";
			if (strlen($hex)==1) {
				 $string .= chr(hexdec($hex));	
			}
			for ($i=0; $i < strlen($hex)-1; $i+=2){
				$string .= chr(hexdec($hex[$i].$hex[$i+1]));
			}
			return $string;
		}
		
		protected function CreateScriptResetIdentify ()	{				//erstellt Script und Timer zum Rücksetzen der Indetify-Variable
			$Identify_ID=IPS_GetVariableIDByName("Identify",$this->InstanceID); 
			if (@IPS_GetVariableIDByName("Speicherplatz",$Identify_ID)!=false) {
				if (IPS_GetVariableIDByName("Speicherplatz",$this->InstanceID)!=false) {
					IPS_DeleteVariable(IPS_GetVariableIDByName("Speicherplatz",$this->InstanceID));
				}	
			}	
			elseif (IPS_GetVariableIDByName("Speicherplatz",$this->InstanceID)!=false) {
				@IPS_SetParent(IPS_GetVariableIDByName("Speicherplatz",$this->InstanceID),$Identify_ID);
			}	
			if (@IPS_GetScriptIDByName("ResetIdentify",$Identify_ID)!=false) return;
			$scriptid = $this->RegisterScript("ResetIdentify", "ResetIdentify",  
			'<?
$Par_ID=IPS_GetParent($_IPS[\'SELF\']);
$Name=IPS_GetName(IPS_GetParent($Par_ID));
if($_IPS[\'SENDER\'] == "TimerEvent") {
	SetValueBoolean($Par_ID,false);
	IPS_LogMessage($Name,"IDENTIFY über Timer wieder ausgeschaltet (".$_IPS[\'SELF\'].")");
	IPS_SetScriptTimer($_IPS[\'SELF\'],0);
}	
elseif (GetValueBoolean($Par_ID)==true) {
	IPS_SetScriptTimer($_IPS[\'SELF\'],20);
}	
?>'
			, -3);		
			IPS_LogMessage("Fingerprintreader","Identify: $Identify_ID");
			@IPS_SetParent($scriptid,$Identify_ID);
			IPS_SetHidden($scriptid,true);	
			// und neuer Timer
			$eid = IPS_CreateEvent(0);                  	//Ausgelöstes Ereignis
			IPS_SetEventTrigger($eid, 1, $Identify_ID);       //Bei Änderung von Variable mit ID 
			@IPS_SetParent($eid, $scriptid);         	//Ereignis zuordnen
			IPS_SetEventActive($eid, true);             	//Ereignis aktivieren	
		}
	
		protected function CreateScriptLED_Ein ()	{					//erstellt Script für LED ein (zum Test für Module-Beginner)
			$LED_ID=IPS_GetVariableIDByName("LED",$this->InstanceID); 
			if (@IPS_GetScriptIDByName("einschalten",$LED_ID)!=false) return;
			$scriptid = $this->RegisterScript("einschalten", "einschalten", 
			'<?
$Par_ID=IPS_GetParent($_IPS[\'SELF\']);
$Name=IPS_GetName($Par_ID);
$Instanz_ID=IPS_GetParent($Par_ID);
$erg=FPgt511_LEDein($Instanz_ID);
if ($erg) IPS_LogMessage($Name,"$Name erfolgreich eingeschaltet (".$_IPS['SELF'].")");
else IPS_LogMessage($Name,"$Name nicht erfolgreich eingeschaltet (".$_IPS['SELF'].")");
// 
//
?>'
			, -8);	
			$LED_ID=IPS_GetVariableIDByName("LED",$this->InstanceID);			
			@IPS_SetParent($scriptid,$LED_ID);
			//IPS_SetHidden($scriptid,true);
		}
			
		protected function CreateScriptLED_Aus ()	{					//erstellt Script für LED ein (zum Test für Module-Beginner)
			$LED_ID=IPS_GetVariableIDByName("LED",$this->InstanceID); 
			if (@IPS_GetScriptIDByName("ausschalten",$LED_ID)!=false) return;		
			$scriptid = $this->RegisterScript("ausschalten", "ausschalten", 
			'<?
$Par_ID=IPS_GetParent($_IPS[\'SELF\']);
$Name=IPS_GetName($Par_ID);
$Instanz_ID=IPS_GetParent($Par_ID);
$erg=FPgt511_LEDaus($Instanz_ID);
if ($erg) IPS_LogMessage($Name,"$Name erfolgreich ausgeschaltet (".$_IPS['SELF'].")");
else IPS_LogMessage($Name,"$Name nicht erfolgreich ausgeschaltet (".$_IPS['SELF'].")");
IPS_SetScriptTimer ($_IPS[\'SELF\'],0);
//
?>'
			, -4);	
			$LED_ID=IPS_GetVariableIDByName("LED",$this->InstanceID);			
			@IPS_SetParent($scriptid,$LED_ID);
			//IPS_SetHidden($scriptid,true);
		}			
	}		
?>
