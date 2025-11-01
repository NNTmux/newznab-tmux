#!/bin/bash
# Quick Font Installation Script for Tmux
# Installs powerline fonts for better tmux appearance

echo "🎨 Tmux Font Installer"
echo "====================="
echo ""

# Check if running in WSL or native Linux
if grep -qi microsoft /proc/version; then
    echo "ℹ️  Detected WSL environment"
    echo "⚠️  Note: You'll also need to configure your Windows Terminal font"
    echo ""
fi

# Function to install powerline fonts
install_powerline() {
    echo "📦 Installing Powerline Fonts..."

    if command -v apt-get &> /dev/null; then
        # Debian/Ubuntu
        sudo apt-get update
        sudo apt-get install -y fonts-powerline
        echo "✅ Powerline fonts installed via apt"
    elif command -v pacman &> /dev/null; then
        # Arch Linux
        sudo pacman -S --noconfirm powerline-fonts
        echo "✅ Powerline fonts installed via pacman"
    elif command -v dnf &> /dev/null; then
        # Fedora
        sudo dnf install -y powerline-fonts
        echo "✅ Powerline fonts installed via dnf"
    else
        # Install from source
        echo "📥 Installing from source..."
        git clone https://github.com/powerline/fonts.git --depth=1
        cd fonts
        ./install.sh
        cd ..
        rm -rf fonts
        echo "✅ Powerline fonts installed from source"
    fi
}

# Function to install nerd fonts
install_nerd_fonts() {
    echo ""
    echo "📦 Installing Nerd Fonts (FiraCode)..."

    # Create fonts directory
    mkdir -p ~/.local/share/fonts

    # Download FiraCode Nerd Font
    cd ~/.local/share/fonts

    if command -v wget &> /dev/null; then
        wget -q --show-progress https://github.com/ryanoasis/nerd-fonts/releases/latest/download/FiraCode.zip
    elif command -v curl &> /dev/null; then
        curl -L -o FiraCode.zip https://github.com/ryanoasis/nerd-fonts/releases/latest/download/FiraCode.zip
    else
        echo "❌ Neither wget nor curl found. Please install one and try again."
        return 1
    fi

    # Extract
    if command -v unzip &> /dev/null; then
        unzip -q FiraCode.zip
        rm FiraCode.zip
        echo "✅ FiraCode Nerd Font installed"
    else
        echo "❌ unzip not found. Please install unzip and try again."
        return 1
    fi

    cd - > /dev/null
}

# Function to refresh font cache
refresh_fonts() {
    echo ""
    echo "🔄 Refreshing font cache..."
    fc-cache -fv > /dev/null 2>&1
    echo "✅ Font cache refreshed"
}

# Function to test fonts
test_fonts() {
    echo ""
    echo "🧪 Testing font symbols..."
    echo ""
    echo "Powerline arrows:  "
    echo "Icons: ❐      "
    echo ""
    echo "If you see boxes or question marks, the fonts aren't working."
    echo "Make sure your terminal is configured to use a Nerd Font."
}

# Main installation
echo "Choose installation option:"
echo "1) Powerline Fonts Only (lightweight, ~5MB)"
echo "2) Nerd Fonts (FiraCode) (complete, ~50MB)"
echo "3) Both (recommended)"
echo ""
read -p "Enter choice [1-3]: " choice

case $choice in
    1)
        install_powerline
        ;;
    2)
        install_nerd_fonts
        ;;
    3)
        install_powerline
        install_nerd_fonts
        ;;
    *)
        echo "❌ Invalid choice"
        exit 1
        ;;
esac

refresh_fonts
test_fonts

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Font installation complete!"
echo ""
echo "📋 Next steps:"
echo ""
echo "1. Configure your terminal to use 'FiraCode Nerd Font Mono'"
echo ""
echo "   Windows Terminal (WSL):"
echo "   - Open Settings (Ctrl+,)"
echo "   - Select your WSL profile"
echo "   - Appearance → Font face → FiraCode Nerd Font Mono"
echo ""
echo "   Gnome Terminal:"
echo "   - Preferences → Profile → Text → Custom font"
echo "   - Select 'FiraCode Nerd Font Mono'"
echo ""
echo "2. Reload tmux configuration:"
echo "   tmux source-file ~/.tmux.conf"
echo ""
echo "3. Or restart tmux:"
echo "   php artisan tmux:stop --force"
echo "   php artisan tmux:start"
echo ""
echo "📖 Full guide: TMUX_FONTS_GUIDE.md"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

