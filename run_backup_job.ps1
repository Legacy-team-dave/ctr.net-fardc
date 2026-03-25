# ================================================================================
# Exécution du job de sauvegarde consolidée CTR.NET-FARDC
# ================================================================================
param(
    [int]$MaxKeep = 30,
    [string]$MailConfigPath = ''
)

$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$cronScript = Join-Path $projectRoot 'includes\backup_cron.php'
$defaultMailConfigPath = Join-Path $projectRoot 'config\backup_mail.json'

if ([string]::IsNullOrWhiteSpace($MailConfigPath)) {
    $MailConfigPath = $defaultMailConfigPath
}

if (-not (Test-Path $cronScript)) {
    Write-Error "Script introuvable: $cronScript"
    exit 1
}

$phpExe = $null
$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($phpCmd) {
    $phpExe = $phpCmd.Source
}

if (-not $phpExe) {
    $candidates = Get-ChildItem -Path 'C:\laragon\bin\php' -Filter 'php.exe' -Recurse -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending
    if ($candidates -and $candidates.Count -gt 0) {
        $phpExe = $candidates[0].FullName
    }
}

if (-not $phpExe) {
    Write-Error "PHP introuvable. Installez/configurez PHP (Laragon ou PATH)."
    exit 1
}

if ($MaxKeep -le 0) {
    $MaxKeep = 30
}

function Get-BackupMailSettings {
    param(
        [string]$ConfigPath
    )

    $settings = [ordered]@{
        Enabled = $false
        SmtpHost = ''
        SmtpPort = 587
        UseSsl = $true
        SmtpUser = ''
        SmtpPassword = ''
        From = ''
        To = ''
        SubjectPrefix = '[CTR.NET-FARDC]'
    }

    if (Test-Path $ConfigPath) {
        try {
            $json = Get-Content -Path $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
            if ($null -ne $json.enabled) { $settings.Enabled = [bool]$json.enabled }
            if ($json.smtpHost) { $settings.SmtpHost = [string]$json.smtpHost }
            if ($json.smtpPort) { $settings.SmtpPort = [int]$json.smtpPort }
            if ($null -ne $json.useSsl) { $settings.UseSsl = [bool]$json.useSsl }
            if ($json.smtpUser) { $settings.SmtpUser = [string]$json.smtpUser }
            if ($json.smtpPassword) { $settings.SmtpPassword = [string]$json.smtpPassword }
            if ($json.from) { $settings.From = [string]$json.from }
            if ($json.to) { $settings.To = [string]$json.to }
            if ($json.subjectPrefix) { $settings.SubjectPrefix = [string]$json.subjectPrefix }
        } catch {
            Write-Warning "Configuration e-mail invalide ($ConfigPath): $($_.Exception.Message)"
        }
    }

    if ($env:CTR_BACKUP_MAIL_ENABLED) { $settings.Enabled = ($env:CTR_BACKUP_MAIL_ENABLED -match '^(1|true|yes|on)$') }
    if ($env:CTR_BACKUP_SMTP_HOST) { $settings.SmtpHost = $env:CTR_BACKUP_SMTP_HOST }
    if ($env:CTR_BACKUP_SMTP_PORT) { $settings.SmtpPort = [int]$env:CTR_BACKUP_SMTP_PORT }
    if ($env:CTR_BACKUP_SMTP_SSL) { $settings.UseSsl = ($env:CTR_BACKUP_SMTP_SSL -match '^(1|true|yes|on)$') }
    if ($env:CTR_BACKUP_SMTP_USER) { $settings.SmtpUser = $env:CTR_BACKUP_SMTP_USER }
    if ($env:CTR_BACKUP_SMTP_PASSWORD) { $settings.SmtpPassword = $env:CTR_BACKUP_SMTP_PASSWORD }
    if ($env:CTR_BACKUP_MAIL_FROM) { $settings.From = $env:CTR_BACKUP_MAIL_FROM }
    if ($env:CTR_BACKUP_MAIL_TO) { $settings.To = $env:CTR_BACKUP_MAIL_TO }
    if ($env:CTR_BACKUP_MAIL_SUBJECT_PREFIX) { $settings.SubjectPrefix = $env:CTR_BACKUP_MAIL_SUBJECT_PREFIX }

    return [pscustomobject]$settings
}

function Send-BackupMail {
    param(
        [string]$AttachmentPath,
        [object]$MailSettings
    )

    if (-not $MailSettings.Enabled) {
        Write-Host "E-mail backup: désactivé (enabled=false)."
        return
    }

    if ([string]::IsNullOrWhiteSpace($MailSettings.SmtpHost) -or [string]::IsNullOrWhiteSpace($MailSettings.From) -or [string]::IsNullOrWhiteSpace($MailSettings.To)) {
        Write-Warning "E-mail backup non envoyé: smtpHost/from/to manquants."
        return
    }

    if (-not (Test-Path $AttachmentPath)) {
        Write-Warning "E-mail backup non envoyé: archive introuvable ($AttachmentPath)."
        return
    }

    $mail = New-Object System.Net.Mail.MailMessage
    $mail.From = $MailSettings.From
    foreach ($dest in ($MailSettings.To -split ';|,')) {
        if (-not [string]::IsNullOrWhiteSpace($dest)) {
            $mail.To.Add($dest.Trim())
        }
    }
    $mail.Subject = "$($MailSettings.SubjectPrefix) Sauvegarde consolidée $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    $mail.Body = "Archive de sauvegarde consolidée CTR.NET-FARDC en pièce jointe.`r`nFichier: $(Split-Path -Leaf $AttachmentPath)"
    $mail.Attachments.Add((New-Object System.Net.Mail.Attachment($AttachmentPath)))

    $smtp = New-Object System.Net.Mail.SmtpClient($MailSettings.SmtpHost, [int]$MailSettings.SmtpPort)
    $smtp.EnableSsl = [bool]$MailSettings.UseSsl

    if (-not [string]::IsNullOrWhiteSpace($MailSettings.SmtpUser)) {
        $smtp.Credentials = New-Object System.Net.NetworkCredential($MailSettings.SmtpUser, $MailSettings.SmtpPassword)
    }

    try {
        $smtp.Send($mail)
        Write-Host "E-mail backup envoyé à: $($MailSettings.To)"
    } catch {
        Write-Warning "E-mail backup échec: $($_.Exception.Message)"
    } finally {
        $mail.Dispose()
        $smtp.Dispose()
    }
}

& $phpExe $cronScript $MaxKeep
$phpExitCode = $LASTEXITCODE
if ($phpExitCode -ne 0) {
    exit $phpExitCode
}

$mailSettings = Get-BackupMailSettings -ConfigPath $MailConfigPath
$backupZip = Join-Path $projectRoot 'backups\backup_consolide_latest.zip'
Send-BackupMail -AttachmentPath $backupZip -MailSettings $mailSettings

exit 0
