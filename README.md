## FPgt511 - Fingerprint Reader GT511C3
Fingerprintreader-Modul GT-511C3 für IP-Symcon (www.ip-symcon.de), entwickelt und getestet auf RaspberryPI, sollte aber an jeder seriellen Schnittstelle funktionieren.

### Beschreibung dieses IPS-Moduls "Fingerprint Reader GT511C3":
1. Dieses Modul kann überall im IPS-Baum hinzugefügt werden, es muss aber der zugehörige Serial-Port als übergeordnete Instanz ausgewählt werden.

2. Mittels der Funktionen im Testbereich des Moduls kann auch das Anlernen erfolgen (LED muss ein sein). 

3. Im Normalbetrieb erweist sich ein zyklischer Aufruf von "FPgt511_IsFingerPress($ID_Instanz);" als sinnvolle Variante, bei Erfolg kann mittels  "FPgt511_Identify($ID_Instanz);" der Fingerabdruck überprüft werden.

4. Unterhalb des Moduls werden vier Variablen erzeugt (Identify, LED, Speicherplatz und Firmwaredatum). Die Identify-Variable wird bei erfolgreicher Identifizierung auf "true" gesetzt und per Script und Timer nach 20 Sekunden wieder zurückgesetzt. Somit kann auf diese Variable auch getriggert werden.  Der Speicherplatz enthält den letzten erkannten Speicherplatz eines Fingerabdrucks.  

### Anschluss des Fingerprintreaders GT511C3 an einen Raspberry-PI:
Der Anschluss ist denkbar einfach, der Fingerprintreader GT-511C3 wird direkt, also ohne weitere Bauelemente, an den RaspberryPI angeschlossen. 
In folgender PDF-Datei ist der Hardwareanschluss dargestellt: https://github.com/herbert-f/HFmodule/blob/master/FPgt511/manual/Anschlussplan_GT511.pdf.

Wen auch das Datenblatt des Fingerprintreader interessiert: (Datasheet: https://www.sparkfun.com/products/11792)

### Bezug Fingerprintreader und Adapterkabel:
Der Fingerprintreader GT-511C3 ist online erhältlich, ich habe diesen hier bezogen: https://www.electronic-shop.lu/DE/products/152040.
 Wichtig ist, ein passendes Kabel (https://www.electronic-shop.lu/DE/products/152414) zu bestellen, ein Löten am Modul erscheint mir nicht sinnvoll.

### Aufruf der Funktionen des Moduls

### *bool* FPgt511_SetLED(*int* $ID_Instanz, *bool* $Status);
	Schaltet LED ein bzw. aus 
	Parameter:   $Status: true= ein, false = aus
	Rückgabe:	Konnte der Befehl erfolgreich ausgeführt werden, liefert er als Ergebnis TRUE, andernfalls FALSE.

### *int* FPgt511_GetEnrollCount(*int* $ID_Instanz);
	Ermittelt Anzahl belegter Speicherplätze
    Rückgabe: Anzahl belegter Speicherplätze in der DB
	
### *bool* FPgt511_Identify($ID_Instanz);  //(LED muss ein sein)
	Überprüfung ob Fingerabdruck in Datenbank
	Rückgabe:	Wurde der Fingerabdruck als ein gespeicherter erkannt,liefert die Funktion als Ergebnis TRUE, andernfalls FALSE. Speicherung der Nummer des Speicherplatzes in separater Variable,(somit Identifizierung der Person möglich)	

#### *bool* FPgt511_Enrollment($ID_Instanz);  //(LED muss ein sein)
	Anlernen (Registrieren) eines Fingerabdrucks (dauert ca. 45sec)
	Rückgabe: Wurde ein FIngerabdruck komplett eingelesen, liefert die Funktion	als Ergebnis TRUE, andernfalls FALSE.

#### *bool* FPgt511_IsFingerPress($ID_Instanz);  //(LED muss ein sein)
	Rückgabe: Wurde ein gedrückter Finger erkannt, liefert die Funktion	als Ergebnis TRUE, andernfalls FALSE.

#### *bool* FPgt511_CheckEnrolled($ID_Instanz, *int* $Speicherplatz);
	Rückgabe: Ist der Speicherplatz schon belegt, liefert die Funktion als Ergebnis TRUE, andernfalls FALSE.

#### *bool* FPgt511_Open($ID_Instanz, *bool* $Info);
	OPEN (Initialisierung und Möglickeit Infos abzufragen)
	Parameter: 	$Info: true= mit Infos (Firmwaredatum, Seriennummer), false = ohne Infos
	Rückgabe:	Konnte der Befehl erfolgreich ausgeführt werden, liefert er als Ergebnis TRUE, andernfalls FALSE.

#### *bool* FPgt511_Close($ID_Instanz);
	Funktion derzeit ohne Sinn

#### *bool* FPgt511_DeleteAll($ID_Instanz);
	Löscht alle Fingerprints aus der DB
	Rückgabe:	Wenn alle Fingerabdrücke in der Datenbank gelöscht wurden, liefert die Funktion als Ergebnis TRUE, andernfalls FALSE.

#### *bool* FPgt511_DeleteID($ID_Instanz, *int* $Speicherplatz);
	Löscht einen Fingerprint aus der DB
	Rückgabe:	Wenn der Fingerabdruck ($Speicherplatz) in der Datenbank gelöscht wurde, liefert die Funktion als Ergebnis TRUE, andernfalls FALSE.
