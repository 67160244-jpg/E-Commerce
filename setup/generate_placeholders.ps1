Add-Type -AssemblyName System.Drawing

$csvPath = "C:\xampp\htdocs\E-Commerce\data\products.csv"
$outputDir = "C:\xampp\htdocs\E-Commerce\assets\products"

if (!(Test-Path $outputDir)) {
    New-Item -ItemType Directory -Force -Path $outputDir
}

$palette = @{
    "1" = @{ Bg = [System.Drawing.Color]::FromArgb(235, 248, 240); Border = [System.Drawing.Color]::FromArgb(74, 189, 131); Text = [System.Drawing.Color]::FromArgb(30, 110, 75); Cat = "Multivitamin" }
    "2" = @{ Bg = [System.Drawing.Color]::FromArgb(255, 247, 230); Border = [System.Drawing.Color]::FromArgb(255, 179, 71); Text = [System.Drawing.Color]::FromArgb(190, 100, 20); Cat = "Vitamin C & Immunity" }
    "3" = @{ Bg = [System.Drawing.Color]::FromArgb(240, 247, 255); Border = [System.Drawing.Color]::FromArgb(90, 160, 245); Text = [System.Drawing.Color]::FromArgb(30, 80, 160); Cat = "Minerals" }
    "4" = @{ Bg = [System.Drawing.Color]::FromArgb(235, 248, 255); Border = [System.Drawing.Color]::FromArgb(70, 190, 220); Text = [System.Drawing.Color]::FromArgb(20, 100, 130); Cat = "Omega & Fish Oil" }
    "5" = @{ Bg = [System.Drawing.Color]::FromArgb(243, 240, 255); Border = [System.Drawing.Color]::FromArgb(160, 130, 240); Text = [System.Drawing.Color]::FromArgb(90, 60, 170); Cat = "Probiotics & Digestive" }
    "6" = @{ Bg = [System.Drawing.Color]::FromArgb(255, 240, 245); Border = [System.Drawing.Color]::FromArgb(255, 130, 170); Text = [System.Drawing.Color]::FromArgb(180, 50, 100); Cat = "Beauty & Skin" }
    "7" = @{ Bg = [System.Drawing.Color]::FromArgb(245, 245, 245); Border = [System.Drawing.Color]::FromArgb(150, 160, 175); Text = [System.Drawing.Color]::FromArgb(70, 80, 95); Cat = "Joint & Bone" }
    "8" = @{ Bg = [System.Drawing.Color]::FromArgb(255, 243, 235); Border = [System.Drawing.Color]::FromArgb(255, 120, 80); Text = [System.Drawing.Color]::FromArgb(180, 60, 30); Cat = "Protein & Sports" }
}

$defaultStyle = @{ Bg = [System.Drawing.Color]::FromArgb(245, 247, 250); Border = [System.Drawing.Color]::FromArgb(200, 205, 215); Text = [System.Drawing.Color]::FromArgb(80, 90, 100); Cat = "Mochi Vita" }

$products = Import-Csv -Path $csvPath -Encoding utf8

function Draw-Card($filePath, $sku, $brand, $titleEn, $catName, $style) {
    $width = 600
    $height = 600
    $bmp = New-Object System.Drawing.Bitmap($width, $height)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

    $bgBrush = New-Object System.Drawing.SolidBrush($style.Bg)
    $g.FillRectangle($bgBrush, 0, 0, $width, $height)

    $pen = New-Object System.Drawing.Pen($style.Border, 8)
    $g.DrawRectangle($pen, 12, 12, $width - 24, $height - 24)

    $innerBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
    $g.FillRectangle($innerBrush, 40, 40, $width - 80, $height - 80)
    $innerPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(230, 235, 242), 2)
    $g.DrawRectangle($innerPen, 40, 40, $width - 80, $height - 80)

    $badgeBrush = New-Object System.Drawing.SolidBrush($style.Border)
    $g.FillRectangle($badgeBrush, 70, 70, 460, 42)

    $badgeFont = New-Object System.Drawing.Font("Arial", 14, [System.Drawing.FontStyle]::Bold)
    $whiteBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
    $sfCenter = New-Object System.Drawing.StringFormat
    $sfCenter.Alignment = [System.Drawing.StringAlignment]::Center
    $sfCenter.LineAlignment = [System.Drawing.StringAlignment]::Center
    $g.DrawString("$brand  -  $catName", $badgeFont, $whiteBrush, (New-Object System.Drawing.RectangleF(70, 70, 460, 42)), $sfCenter)

    $bottleBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(242, 246, 252))
    $bottlePen = New-Object System.Drawing.Pen($style.Border, 4)
    $g.FillRectangle($bottleBrush, 260, 140, 80, 30)
    $g.DrawRectangle($bottlePen, 260, 140, 80, 30)
    $g.FillRectangle($bottleBrush, 230, 170, 140, 150)
    $g.DrawRectangle($bottlePen, 230, 170, 140, 150)

    $plusBrush = New-Object System.Drawing.SolidBrush($style.Border)
    $g.FillRectangle($plusBrush, 290, 215, 20, 60)
    $g.FillRectangle($plusBrush, 270, 235, 60, 20)

    $titleFont = New-Object System.Drawing.Font("Arial", 18, [System.Drawing.FontStyle]::Bold)
    $titleBrush = New-Object System.Drawing.SolidBrush($style.Text)
    $g.DrawString($titleEn, $titleFont, $titleBrush, (New-Object System.Drawing.RectangleF(60, 350, 480, 80)), $sfCenter)

    $skuFont = New-Object System.Drawing.Font("Arial", 13, [System.Drawing.FontStyle]::Regular)
    $skuBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(120, 130, 140))
    $g.DrawString("SKU: $sku  |  Mochi Vita", $skuFont, $skuBrush, (New-Object System.Drawing.RectangleF(60, 470, 480, 40)), $sfCenter)

    $bmp.Save($filePath, [System.Drawing.Imaging.ImageFormat]::Jpeg)

    $g.Dispose()
    $bmp.Dispose()
}

foreach ($p in $products) {
    $imgRel = $p.image
    if ([string]::IsNullOrWhiteSpace($imgRel)) { continue }
    $fileName = Split-Path -Leaf $imgRel
    $targetFile = Join-Path $outputDir $fileName

    $catId = $p.category_id
    $st = $palette[$catId]
    if ($null -eq $st) { $st = $defaultStyle }

    Draw-Card -filePath $targetFile -sku $p.sku -brand $p.brand -titleEn $p.name_en -catName $st.Cat -style $st
    Write-Host "Created: $fileName"
}

$fallbackPath = Join-Path $outputDir "placeholder.jpg"
Draw-Card -filePath $fallbackPath -sku "MV-DEFAULT" -brand "Mochi Vita" -titleEn "Product Image" -catName "Supplements" -style $defaultStyle
Write-Host "Created fallback: placeholder.jpg"
