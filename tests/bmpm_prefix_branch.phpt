--TEST--
bmpm(): generic d' and name-prefix branching produces (...)-(...)
--EXTENSIONS--
phonetic
--FILE--
<?php
echo bmpm("d'angelo", BMPM_GENERIC, BMPM_EXACT), "\n";
echo bmpm("de la cruz"), "\n";
echo bmpm("van der berg"), "\n";
?>
--EXPECT--
(anZelo|andZelo|angelo|anhelo|anjelo|anxelo)-(danZelo|dandZelo|dangelo|danhelo|danjelo|danxelo)
(lakru|lakruS|lakrus|lakruts|latzrus|lazrus|lokru|lokruS|lokrus|lokruts|lotzrus|lozrus)-((kru|kruS|krus|kruts|tzrus|zrus)-(dilakru|dilakruS|dilakrus|dilakruts|dilatzrus|dilazrus|dilokru|dilokruS|dilokrus|dilokruts|dilotzrus|dilozrus))
(dYrbYrk|dYrbirk|dirbYrk|dirbirk|dirvirk)-(bandirbirk|bandirvirk|bondirbirk|bondirvirk|fYndYrbYrk|fYndYrbirk|fYndirbYrk|fYndirbirk|fandYrbYrk|fandYrbirk|fandirbYrk|fandirbirk|fondYrbYrk|fondYrbirk|fondirbYrk|fondirbirk|vYndYrbYrk|vYndYrbirk|vYndirbYrk|vYndirbirk|vandYrbYrk|vandYrbirk|vandirbYrk|vandirbirk|vandirvirk|vondYrbYrk|vondYrbirk|vondirbYrk|vondirbirk|vondirvirk)
