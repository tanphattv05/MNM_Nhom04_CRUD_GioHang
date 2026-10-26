# Called only by AccountPasswordCommand. Never pass passwords as arguments.
$ErrorActionPreference = 'Stop'
$previousControlMode = $null
try {
    if ([Console]::IsInputRedirected) { exit 1 }
    $previousControlMode = [Console]::TreatControlCAsInput
    [Console]::TreatControlCAsInput = $true
    $buffer = New-Object System.Text.StringBuilder
    while ($true) {
        $key = [Console]::ReadKey($true)
        if ($key.Key -eq [ConsoleKey]::Escape -or
            ($key.Key -eq [ConsoleKey]::C -and ($key.Modifiers -band [ConsoleModifiers]::Control))) {
            exit 1
        }
        if ($key.Key -eq [ConsoleKey]::Enter) { break }
        if ($key.Key -eq [ConsoleKey]::Backspace) {
            if ($buffer.Length -gt 0) {
                $buffer.Length--
                [Console]::Error.Write("`b `b")
            }
        } elseif (-not [char]::IsControl($key.KeyChar)) {
            [void]$buffer.Append($key.KeyChar)
            [Console]::Error.Write('*')
        }
    }
    # Only the parent PHP process receives this value; it is never displayed.
    [Console]::Out.Write([Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($buffer.ToString())))
} catch {
    # Do not print exception context that could include secret data.
    exit 1
} finally {
    if ($null -ne $previousControlMode) { [Console]::TreatControlCAsInput = $previousControlMode }
    if ($null -ne $buffer) { [void]$buffer.Clear() }
    [Console]::Error.WriteLine()
}
