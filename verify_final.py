import asyncio
from playwright.async_api import async_playwright

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        context = await browser.new_context(viewport={'width': 1280, 'height': 720})
        page = await context.new_page()

        print("Navigating to login page...")
        await page.goto("http://localhost:3000/login.php")

        print("Logging in...")
        await page.fill("#emailInput", "admin@dentalpos.com")
        await page.fill("#passwordInput", "admin123")
        await page.click("button[type='submit']")

        print("Waiting for dashboard to load...")
        await page.wait_for_selector("#currentViewTitle", timeout=10000)

        print("Navigating to POS view...")
        await page.goto("http://localhost:3000/index.php#pos")
        await page.wait_for_selector("#pos-cart-items")
        await page.wait_for_timeout(2000) # give it time to load products and render cart

        print("Taking screenshot of empty cart state...")
        await page.screenshot(path="verification_empty_cart.png")

        print("Closing browser...")
        await context.close()
        await browser.close()
        print("Test passed successfully!")

if __name__ == "__main__":
    asyncio.run(main())
