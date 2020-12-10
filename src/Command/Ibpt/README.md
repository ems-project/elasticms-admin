# Import IBPT

```ssh
php bin/console ems:import:ibpt import_ibpt -vvv
php bin/console ems:contenttype:migrate import_ibpt radio_application 
php bin/console ems:contenttype:migrate import_ibpt radio_attribution 
php bin/console ems:contenttype:migrate import_ibpt frequency 
```