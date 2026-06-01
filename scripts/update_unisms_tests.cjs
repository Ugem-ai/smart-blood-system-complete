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
const replacements = [
  [/('services\.twilio\.sid'\s*=>\s*'[^']*')/g, "'services.unisms.api_key' => 'test-api-key'"],
  [/('services\.twilio\.token'\s*=>\s*'[^']*')/g, "'services.unisms.sender_id' => 'PRCSMS'"],
  [/('services\.twilio\.from'\s*=>\s*'[^']*')/g, "'services.unisms.sender_id' => 'PRCSMS'"],
  [/https:\/\/api\.twilio\.com\/\*/g, 'https://unismsapi.com/*'],
  [/str_contains\(\$request->url\(\), 'api\.twilio\.com'\)/g, "str_contains($request->url(), 'unismsapi.com')"],
  [/api\.twilio\.com/g, 'unismsapi.com'],
];

for (const file of files) {
  const p = path.resolve(file);
  if (!fs.existsSync(p)) {
    console.log('MISSING', file);
    continue;
  }
  let content = fs.readFileSync(p, 'utf8');
  let updated = content;
  for (const [regex, replacement] of replacements) {
    updated = updated.replace(regex, replacement);
  }
  if (updated !== content) {
    fs.writeFileSync(p, updated, 'utf8');
    console.log('Updated', file);
  } else {
    console.log('No change in', file);
  }
}
