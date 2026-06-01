const fs = require('fs');
const path = require('path');
const files = [
  'tests/Feature/SystemValidationPipelineTest.php',
  'tests/Feature/EmergencyBroadcastModeTest.php',
  'tests/Feature/Phase23PerformanceTest.php',
  'tests/Feature/Phase30EmergencyBroadcastModeTest.php',
  'tests/Feature/PerformanceTest.php',
  'tests/Feature/NotificationReliabilityTest.php',
];
const search = "'services.unisms.sender_id' => 'PRCSMS',\r\n            'services.unisms.sender_id' => 'PRCSMS',";
const replace = "'services.unisms.sender_id' => 'PRCSMS',\r\n";
for (const file of files) {
  const p = path.resolve(file);
  if (!fs.existsSync(p)) {
    console.log('MISSING', file);
    continue;
  }
  const text = fs.readFileSync(p, 'utf8');
  const updated = text.split(search).join(replace);
  if (updated !== text) {
    fs.writeFileSync(p, updated, 'utf8');
    console.log('Fixed duplicates in', file);
  } else {
    console.log('No duplicate found in', file);
  }
}
