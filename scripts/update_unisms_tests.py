from pathlib import Path
import re

files = [
    'tests/Feature/SystemValidationPipelineTest.php',
    'tests/Feature/EmergencyBroadcastModeTest.php',
    'tests/Feature/Phase23PerformanceTest.php',
    'tests/Feature/Phase30EmergencyBroadcastModeTest.php',
    'tests/Feature/PerformanceTest.php',
    'tests/Feature/NotificationReliabilityTest.php',
]

replacements = [
    (r"'services\.twilio\.sid'\s*=>\s*'[^']*'", "'services.unisms.api_key' => 'test-api-key'"),
    (r"'services\.twilio\.token'\s*=>\s*'[^']*'", "'services.unisms.sender_id' => 'PRCSMS'"),
    (r"'services\.twilio\.from'\s*=>\s*'[^']*'", "'services.unisms.sender_id' => 'PRCSMS'"),
    (r"https://api\.twilio\.com/\*", 'https://unismsapi.com/*'),
    (r"str_contains\(\$request->url\(\), 'api\.twilio\.com'\)", "str_contains($request->url(), 'unismsapi.com')"),
    (r"api\.twilio\.com", 'unismsapi.com'),
]

for path_str in files:
    p = Path(path_str)
    if not p.exists():
        print(f'MISSING {path_str}')
        continue
    text = p.read_text(encoding='utf-8')
    original = text
    for pattern, repl in replacements:
        text = re.sub(pattern, repl, text)
    if text != original:
        p.write_text(text, encoding='utf-8')
        print(f'Updated {path_str}')
    else:
        print(f'No change in {path_str}')
