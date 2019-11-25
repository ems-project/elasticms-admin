#!/bin/bash

start=`date +%s`

#webchamber ems:job:chamber $1/ORGN orgn "${@:2}"
#webchamber ems:job:chamber $1/ACTR actr "${@:2}"
#webchamber ems:job:chamber $1/../missing_files/ACTR/Membres/XML actr_members "${@:2}"

#webchamber ems:job:chamber $1/QRVA/xml/47 qrva "${@:2}"
#webchamber ems:job:chamber $1/QRVA/xml/48 qrva "${@:2}"
#webchamber ems:job:chamber $1/QRVA/xml/49 qrva "${@:2}"
#webchamber ems:job:chamber $1/QRVA/xml/50 qrva "${@:2}"
#webchamber ems:job:chamber $1/QRVA/xml/51 qrva "${@:2}"
#webchamber ems:job:chamber $1/QRVA/xml/52 qrva "${@:2}"
#webchamber ems:job:chamber $1/QRVA/xml/53 qrva "${@:2}"
#webchamber ems:job:chamber $1/QRVA/xml/54 qrva "${@:2}"

#webchamber ems:job:chamber $1/FLWB/xml/47 flwb "${@:2}"
#webchamber ems:job:chamber $1/FLWB/xml/48 flwb "${@:2}"
#webchamber ems:job:chamber $1/FLWB/xml/49 flwb "${@:2}"
#webchamber ems:job:chamber $1/FLWB/xml/50 flwb "${@:2}"
#webchamber ems:job:chamber $1/FLWB/xml/51 flwb "${@:2}"
#webchamber ems:job:chamber $1/FLWB/xml/52 flwb "${@:2}"
#webchamber ems:job:chamber $1/FLWB/xml/53 flwb "${@:2}"
#webchamber ems:job:chamber $1/FLWB/xml/54 flwb "${@:2}"

#webchamber ems:job:chamber $1/INQO/XML/47 inqo "${@:2}"
#webchamber ems:job:chamber $1/INQO/XML/48 inqo "${@:2}"
#webchamber ems:job:chamber $1/INQO/XML/49 inqo "${@:2}"
#webchamber ems:job:chamber $1/INQO/XML/50 inqo "${@:2}"
#webchamber ems:job:chamber $1/INQO/XML/51 inqo "${@:2}"
#webchamber ems:job:chamber $1/INQO/XML/52 inqo "${@:2}"
#webchamber ems:job:chamber $1/INQO/XML/53 inqo "${@:2}"
#webchamber ems:job:chamber $1/INQO/XML/54 inqo "${@:2}"

#webchamber ems:job:chamber $1/MTNG/v4.1 mtng "${@:2}"

#webchamber ems:job:chamber $1/CCRA/XML ccra "${@:2}"
#webchamber ems:job:chamber $1/CCRI/XML ccri "${@:2}"
#webchamber ems:job:chamber $1/PCRA/XML pcra "${@:2}"
#webchamber ems:job:chamber $1/PCRI/XML pcri "${@:2}"

end=`date +%s`
runtime=$((end-start))

echo "Runtime was $runtime seconds"