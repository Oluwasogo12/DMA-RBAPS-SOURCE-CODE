$css = Get-Content -Raw -Path "c:\xampp\htdocs\rbaps\css\style.css"

# Add variables to :root
$rootVars = @"
  --slate-alpha-2: rgba(15,23,42,0.02);
  --slate-alpha-3: rgba(15,23,42,0.03);
  --slate-alpha-4: rgba(15,23,42,0.04);
  --slate-alpha-6: rgba(15,23,42,0.06);
  --slate-alpha-7: rgba(15,23,42,0.07);
  --slate-alpha-8: rgba(15,23,42,0.08);
  --slate-alpha-12: rgba(15,23,42,0.12);
  --slate-alpha-25: rgba(15,23,42,0.25);
"@
$css = $css -replace '(--text4:\s*[^;]+;)', "`$1`n$rootVars"

# Add variables to [data-theme="dark"]
$darkVars = @"
  --slate-alpha-2: rgba(255,255,255,0.02);
  --slate-alpha-3: rgba(255,255,255,0.03);
  --slate-alpha-4: rgba(255,255,255,0.04);
  --slate-alpha-6: rgba(255,255,255,0.06);
  --slate-alpha-7: rgba(255,255,255,0.07);
  --slate-alpha-8: rgba(255,255,255,0.08);
  --slate-alpha-12: rgba(255,255,255,0.12);
  --slate-alpha-25: rgba(255,255,255,0.25);
"@
$css = $css -replace '(color-scheme: dark;)', "`$1`n$darkVars"

# Replace instances
$css = $css -replace 'rgba\(15,23,42,0\.02\)', 'var(--slate-alpha-2)'
$css = $css -replace 'rgba\(15,23,42,0\.03\)', 'var(--slate-alpha-3)'
$css = $css -replace 'rgba\(15,23,42,0\.04\)', 'var(--slate-alpha-4)'
$css = $css -replace 'rgba\(15,23,42,0\.06\)', 'var(--slate-alpha-6)'
$css = $css -replace 'rgba\(15,23,42,0\.07\)', 'var(--slate-alpha-7)'
$css = $css -replace 'rgba\(15,23,42,0\.08\)', 'var(--slate-alpha-8)'
$css = $css -replace 'rgba\(15,23,42,0\.12\)', 'var(--slate-alpha-12)'
$css = $css -replace 'rgba\(15,23,42,0\.25\)', 'var(--slate-alpha-25)'

# Also add text-decoration: none to .btn
$css = $css -replace '\.btn\{', ".btn{`n  text-decoration: none;"

Set-Content -Path "c:\xampp\htdocs\rbaps\css\style.css" -Value $css
Write-Output "Done"
