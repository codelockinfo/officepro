# 📱 Phone Number Feature - Complete Guide

## ✅ IMPLEMENTED

---

## 🌍 Country Code Selector

### Features:
- ✅ **Dropdown with country codes**
- ✅ **India (+91) set as DEFAULT**
- ✅ Flags for visual recognition (🇮🇳, 🇺🇸, 🇬🇧, etc.)
- ✅ 14 popular countries included
- ✅ Combines country code + phone number automatically

### Supported Countries:
1. 🇮🇳 India (+91) - **DEFAULT**
2. 🇺🇸 USA (+1)
3. 🇬🇧 UK (+44)
4. 🇦🇺 Australia (+61)
5. 🇨🇳 China (+86)
6. 🇯🇵 Japan (+81)
7. 🇰🇷 Korea (+82)
8. 🇸🇬 Singapore (+65)
9. 🇦🇪 UAE (+971)
10. 🇸🇦 Saudi Arabia (+966)
11. 🇵🇰 Pakistan (+92)
12. 🇧🇩 Bangladesh (+880)
13. 🇱🇰 Sri Lanka (+94)
14. 🇳🇵 Nepal (+977)

---

## 📱 Phone Number Input

### Restrictions:
- ✅ **Exactly 10 digits** (max length enforced)
- ✅ **Only numbers allowed** (letters blocked instantly)
- ✅ Real-time character filtering
- ✅ Visual validation feedback

### Visual Feedback:
- 🔴 **Default (gray border):** Empty field
- 🟡 **Yellow border:** Less than 10 digits
- 🟢 **Green border:** Exactly 10 digits (valid!)

### Validation:
- ✅ **Client-Side:**
  - `oninput` blocks non-numeric characters
  - `maxlength="10"` enforces limit
  - `pattern="[0-9]{10}"` HTML5 validation
  - Real-time color feedback

- ✅ **Server-Side:**
  - Enhanced `Validator::phone()` method
  - Checks character validity
  - Validates 10-15 digit range (flexible for international)
  - Clear error messages

---

## 💾 How It's Stored

### Database Format:
```
+91 1234567890
```

### Components:
- Country Code: `+91`
- Space: ` `
- Phone Number: `1234567890` (10 digits)

### Example Values:
- India: `+91 9876543210`
- USA: `+1 5551234567`
- UK: `+44 7700900123`

---

## 🎯 Where It's Used

### 1. Company Registration
- Path: `company_register.php`
- Country selector + phone input
- India (+91) default
- Combines on submit

### 2. Company Settings
- Path: `app/views/company/settings.php`
- Edit company phone
- Parses existing phone to populate fields
- Same country selector
- Updates on save

### 3. Future: User Profiles (if needed)
- Can be added to employee registration
- Same pattern and validation

---

## 🧪 TESTING

### Test Input Restrictions:

**Try typing in phone field:**
```
abc → Nothing appears (blocked!)
123 → "123" appears (yellow border)
1234567890 → Shows all digits (green border!)
12345678901 → Only shows "1234567890" (max 10)
```

### Test Country Selection:

1. Open registration page
2. **Default shows:** 🇮🇳 India (+91)
3. Change to USA (+1)
4. Enter: 5551234567
5. Submit → Saves as: "+1 5551234567"

### Test Validation:

**Valid Examples:**
- ✅ 9876543210 (India)
- ✅ 5551234567 (USA)
- ✅ 7700900123 (UK)

**Invalid Examples:**
- ❌ 12345 (too short)
- ❌ abc123 (letters blocked)
- ❌ 123-456-7890 (dashes removed automatically)

---

## 🎨 UI Design

### Layout:
```
┌─────────────────────────────────────────┐
│ Phone Number                            │
├─────────────────┬───────────────────────┤
│ 🇮🇳 India (+91) │ 1234567890           │
│       ▼         │                       │
└─────────────────┴───────────────────────┘
  Enter 10-digit mobile number
```

### Features:
- Side-by-side layout (dropdown + input)
- Flags for easy country recognition
- Placeholder shows format
- Helper text below
- Color-coded borders

---

## 💡 Tips for Users

### When Registering:
1. **Select your country** from dropdown (default: India)
2. **Enter 10 digits** in phone field
3. Watch for **green border** (means valid!)
4. Submit form
5. Phone saved as: `+91 1234567890`

### When Editing:
1. Go to Company Settings
2. Existing phone is pre-filled (separated into code + number)
3. Change country or number
4. Save changes

---

## 🔧 Technical Details

### JavaScript:
- Character filtering with regex: `/[^0-9]/g`
- Slice to limit length: `.slice(0, 10)`
- Real-time validation with color feedback
- Auto-combine on form submit

### PHP:
- Validates format: `[\+]?[0-9\s\-\(\)]+`
- Counts digits: 10-15 range
- Sanitizes input
- Stores with country code

### Form Submission:
```javascript
const countryCode = '+91';
const phoneNumber = '1234567890';
const finalPhone = countryCode + ' ' + phoneNumber;
// Result: "+91 1234567890"
```

---

## ✅ COMPLETE IMPLEMENTATION

**Pages with Phone Field:**
1. ✅ `company_register.php` - Company registration
2. ✅ `app/views/company/settings.php` - Company settings

**Features:**
- ✅ Country code dropdown (14 countries)
- ✅ India (+91) as default
- ✅ 10-digit restriction
- ✅ Only numbers allowed
- ✅ Real-time validation
- ✅ Visual feedback (colors)
- ✅ Client & server validation
- ✅ Auto-combine on submit
- ✅ Parse on edit

**Status:** Production Ready! 🎉

