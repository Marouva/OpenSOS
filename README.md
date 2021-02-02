# OpenSOS
Open source knihovna pro tvorbu requestů na Informační Systém SPŠ na Proseku

### Ukázka použití
```php
// REQUEST 1
$sos = new SOS();
$sos->Start();
$sos->UserLogin('username', 'password');

$saved = $sos->SaveSession(); // save to DB

// REQUEST 2
$sos2 = new SOS();
$sos2->LoadSession($saved);

var_dump($sos2->MyClassification());
var_dump($sos2->GetTopicAttendance('2B'));
var_dump($sos2->UserInout('2020-11-2', '2021-2-2'));
```
