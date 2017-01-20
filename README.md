## FPgt511 - Fingerprint Reader GT511C3
Fingerprintreader-Modul GT-511C3 für IP-Symcon (www.ip-symcon.de),
entwickelt und getestet auf RaspberryPI, sollte aber an jeder seriellen 
Schnittstelle funktionieren.

###Beschreibung dieses IPS-Moduls "Fingerprint Reader GT511C3":
1. Dieses Modul kann überall im IPS-Baum hinzugefügt werden, es muss aber der zugehörige Serial-Port als übergeordnete Instanz ausgewählt werden.

2. Mittels der Funktionen im Testbereich des Moduls kann auch das Anlernen erfolgen (LED muss ein sein). 

3. Im Normalbetrieb erweist sich ein zyklischer Aufruf von "FPgt511_IsFingerPress($ID_Instanz);" als sinnvolle Variante, 
bei Erfolg kann mittels  "FPgt511_Identify($ID_Instanz);" der Fingerabdruck überprüft werden.

4. Unterhalb des Moduls werden vier Variablen erzeugt (Identify, LED, Speicherplatz und Firmwaredatum). Die Identify-Variable wird bei erfolgreicher Identifizierung auf "true" gesetzt
und per Script und Timer nach 20 Sekunden wieder zurückgesetzt. Somit kann auf diese Variable auch getriggert werden.
Der Speicherplatz enthält den letzten erkannten Speicherplatz eines Fingerabdrucks.


###Anschluss des Fingerprintreaders GT511C3 an einen Raspberry-PI 2:
Der Anschluss ist denkbar einfach, der Fingerprintreader GT-511C3 wird direkt, also ohne weitere Bauelemente, an den RaspberryPI angeschlossen. 
In folgender PDF-Datei ist der Hardwareanschluss dargestellt: https://github.com/herbert-f/HFmodule/blob/master/FPgt511/manual/Anschlussplan_GT511.pdf.

Wen auch das Datenblatt des Fingerprintreader interessiert: (Datasheet: https://www.sparkfun.com/products/11792)

###Bezug Fingerprintreader und Adapterkabel:
Der Fingerprintreader GT-511C3 ist online erhältlich, ich habe diesen hier bezogen: https://www.electronic-shop.lu/DE/products/152040.
 Wichtig ist, ein passendes Kabel (https://www.electronic-shop.lu/DE/products/152414) zu bestellen, ein Löten am Modul erscheint mir nicht sinnvoll.

###Aufruf der Funktionen des Moduls
####FPgt511_SetLED($ID_Instanz,$Status);
	Schaltet LED ein bzw. aus 
	$Status: true= ein, false = aus

####FPgt511_GetEnrollCount($ID_Instanz);
	Liefert Anzahl belegter Speicherplätze in der DB
	
####FPgt511_Identify($ID_Instanz);  //(LED muss ein sein)
	Überprüfung ob Fingerabdruck in Datenbank
	Liefert TRUE bei Erfolg
	Speicherung der Nummer des Speicherplatzes in separater Variable,
	(somit Identifizierung der Person möglich)	

####FPgt511_Enrollment($ID_Instanz);  //(LED muss ein sein)
	Anlernen (Registrieren) eines Fingerabdrucks (dauert ca. 45sec)
	Liefert TRUE bei Erfolg

####FPgt511_IsFingerPress($ID_Instanz);  //(LED muss ein sein)
	Liefert TRUE wenn Finger auf Fingerprintreader gedrückt ist

####FPgt511_CheckEnrolled($ID_Instanz,$Speicherplatz);
	Liefert TRUE wenn $Speicherplatz schon belegt

####FPgt511_Open($ID_Instanz,$Info);
	OPEN (Initialisierung und Möglickeit Infos abzufragen)
	$Info: true= mit Infos (Firmwaredatum, Seriennummer), false = ohne Infos

####FPgt511_Close($ID_Instanz);
	Funktion derzeit ohne Sinn

####FPgt511_DeleteAll($ID_Instanz);
	Löscht alle Fingerprints aus der DB
	Liefert TRUE wenn alle DB-Einträge gelöscht

####FPgt511_DeleteID($ID_Instanz,$Speicherplatz);
	Löscht einen Fingerprint aus der DB
	Liefert TRUE wenn der Eintrag ($Speicherplatz) gelöscht
