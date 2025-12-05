# 🧪 Testing Company Registration & Profile Photo

## To Test Profile Photo Issue:

### Step 1: Register a New Company
1. **Logout** if logged in
2. Go to: `http://localhost/officepro/company_register.php`
3. Fill in the form with:
   - Company Name: Test Company
   - Company Email: test@company.com
   - Your Name: Test Owner
   - Your Email: owner@test.com
   - Password: password123
   - **Upload a profile photo** (required)
   - Optionally upload company logo
4. Click "Register Company & Create Account"

### Step 2: Check Dashboard
After registration, you should:
- Be automatically logged in
- Redirected to dashboard
- **See your profile photo** in the top-right corner

### Step 3: If Photo Doesn't Show:

#### Check Browser Console (F12):
- Look for errors in Console tab
- Check Network tab for the image request
- See if image URL is correct

#### Check Diagnostic Pages:
1. **Check Profile:** `http://localhost/officepro/check_profile.php`
   - Shows database value
   - Shows if file exists
   - Displays the image

2. **Test Upload:** `http://localhost/officepro/test_direct_upload.php`
   - Test if uploads work at all
   - Shows directory permissions

#### Check Database:
Run this query in phpMyAdmin:
```sql
SELECT id, full_name, email, profile_image FROM users ORDER BY id DESC LIMIT 1;
```

See what profile_image path is stored.

#### Check Files:
Look in folder: `c:\wamp64\www\officepro\uploads\profiles\`
- Should contain uploaded images
- Check file names match database

### Step 4: Debugging Info

The system now logs:
- ✅ Upload attempts with full details
- ✅ Login events with profile image path
- ✅ Dashboard loading with session data

Check: `logs/error.log` for debug information

---

## Expected Behavior:

✅ Profile photo uploaded during registration  
✅ Saved to `uploads/profiles/profile_xxxxx_timestamp.jpg`  
✅ Path stored in database: `uploads/profiles/profile_xxxxx.jpg`  
✅ Session updated with profile image path  
✅ Header shows profile photo (top right corner)  
✅ Dashboard loads and displays profile photo  
✅ All pages show the profile photo  

---

## If Still Not Working:

1. Check `logs/error.log` - look for "Upload" or "Login" entries
2. Check browser DevTools Network tab - see if image is loading
3. Look at the image URL in browser - is the path correct?
4. Visit `check_profile.php` to see database vs session vs files

The enhanced logging will tell us exactly what's happening!

