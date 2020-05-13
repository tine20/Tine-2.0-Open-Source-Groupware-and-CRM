const expect = require('expect-puppeteer');
const lib = require('../../lib/browser');
require('dotenv').config();

beforeAll(async () => {
    //expect.setDefaultOptions({timeout: 1000});
    await lib.getBrowser('Admin');
});

describe('accounts', () => {
    let newPage;
    test('User Mainpage', async () => {
        await page.waitFor(1000);
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Benutzer'});
        await page.screenshot({path: 'screenshots/13_administration/1_admin_benutzertabelle.png'});
    });
    test('choose grid fields', async () => {
        await expect(page).toMatchElement('span', {text: 'Tine 2.0'});
        await page.click('.t-app-admin .ext-ux-grid-gridviewmenuplugin-menuBtn');
        await page.waitFor('.x-menu-list');
        await page.screenshot({
            path: 'screenshots/13_administration/2_admin_spaltenauswahl.png',
            clip: {x: (1366 - (1366 / 5)), y: 0, width: (1366 / 5), height: 768}
        });
    });
    test('new user', async () => {
        await expect(page).toClick('button', {text: 'Benutzer hinzufügen'});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2500);
        await newPage.screenshot({path: 'screenshots/13_administration/3_admin_benutzer_neu.png'});
    });
    test('groups', async () => {
        await newPage.waitFor(1000);
        await expect(newPage).toClick('span', {text: 'Gruppen'});
        await newPage.waitFor(500);
        await newPage.screenshot({path: 'screenshots/13_administration/4_admin_benutzer_gruppe.png'});
        await expect(newPage).toClick('button', {text: 'Abbrechen'});
    });
    test('edit user', async () => {
        let row = await page.$$('#gridAdminUsers .x-grid3-row');
        await row[3].click({clickCount: 2});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2000);
        await newPage.screenshot({path: 'screenshots/13_administration/5_admin_benutzer_editieren.png'});
        await newPage.close();
    });
});

describe('groups', () => {
    test('group mainpage', async () => {
        await page.waitFor(1000);
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Gruppen'});
        await page.waitFor(2000);
        await page.screenshot({path: 'screenshots/13_administration/6_admin_gruppen.png'});
    });
    test('edit group', async () => {
        await expect(page).toClick('.t-app-admin .x-grid3-cell-inner.x-grid3-col-name', {text: 'Users', clickCount: 2});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2000);
        await newPage.screenshot({path: 'screenshots/13_administration/7_admin_gruppen_editieren.png'});
        await expect(newPage).toClick('button', {text: 'Abbrechen'});
    })
});

describe('roles', () => {
    test('roles mainpage', async () => {
        await page.waitFor(1000);
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Rollen'});
        //await page.screenshot({path: 'screenshots/13_administration/6_admin_gruppen.png'});
    });
    test('edit roles', async () => {
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'user role', clickCount: 2});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2000);
        await newPage.screenshot({path: 'screenshots/13_administration/8_admin_rolle_editieren.png'});
        await expect(newPage).toClick('span', {text: 'Rechte'});
        await newPage.waitFor(500);
        await newPage.screenshot({path: 'screenshots/13_administration/9_admin_rolle_rechte_editieren.png'});
        await expect(newPage).toClick('button', {text: 'Abbrechen'});
    })
});

describe('application', () => {
    test('apps mainpage', async () => {
        await page.waitFor(1000);
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Anwendungen'});
        await page.waitFor(2000);
        await page.screenshot({path: 'screenshots/13_administration/10_admin_anwendungen.png'});
    });
    test('addressbook settings', async () => {
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'Adressbuch', clickCount: 2});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2000);
        await newPage.screenshot({path: 'screenshots/13_administration/11_admin_admin_einstellung.png'});
        await newPage.close();
    });
    test('add resousce', async () => {
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'Kalender', clickCount: 2});
        newPage = await lib.getNewWindow();
        await page.waitFor(2000);
        await expect(newPage).toClick('button', {text: 'Ressource hinzufügen'});
        let popup = await lib.getNewWindow();
        await popup.waitFor(2000);
        await popup.screenshot({path: 'screenshots/13_administration/13_admin_kalender_ressource_neu.png'});
        await expect(popup).toClick('span', {text: 'Zugriffsrechte'});
        await popup.waitFor(1000);
        await popup.screenshot({path: 'screenshots/13_administration/14_admin_kalender_ressource_rechte.png'});
        await popup.close();
        await newPage.close();
    });
    test('crm settings', async () => {
        await page.waitFor(1000);
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'Crm', clickCount: 2});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2000);
        await newPage.screenshot({path: 'screenshots/13_administration/15_admin_crm_einstellungen.png'});
        let rows = await newPage.$$('.x-grid3-cell-inner.x-grid3-col-value');
        await rows[3].click({clickCount: 2});
        await newPage.waitFor(2000);
        await newPage.screenshot({path: 'screenshots/13_administration/16_admin_crm_lead_status.png'});
        await newPage.close();
    });
    test('hr settings', async () => {
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'Human Resources', clickCount: 2});
        let dialog = await page.$('.x-window.x-resizable-pinned');
        await dialog.screenshot({path: 'screenshots/13_administration/17_admin_hr_einstellungen.png'});
        await page.keyboard.press('Escape');
    });
    test('sales settings', async () => {
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'Sales', clickCount: 2});
        let dialog = await page.$('.x-window.x-resizable-pinned');
        await dialog.screenshot({path: 'screenshots/13_administration/18_admin_sales_einstellungen.png'});
        await page.keyboard.press('Escape');
    });
    test('tinebase settings', async () => {
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'Tinebase', clickCount: 2});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2000);
        await expect(newPage).toClick('span', {text: 'Profilinformation'});
        await newPage.waitFor(500);
        await newPage.screenshot({path: 'screenshots/13_administration/19_admin_tinebase_einstellungen.png'});
        await newPage.close();
    });
});

describe('container', () => {
    test('container mainpage', async () => {
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Container'});
        await page.waitFor(2000);
        await page.screenshot({path: 'screenshots/13_administration/24_admin_container.png'});
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'Internal Contacts', clickCount: 2});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(3000);
        await newPage.screenshot({path: 'screenshots/13_administration/25_admin_container_editieren.png'});
        await newPage.close();
    });
    test('add container', async () => {
        await expect(page).toClick('button', {text: 'Container hinzufügen'});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(3000);
        await newPage.screenshot({path: 'screenshots/13_administration/26_admin_container_neu.png'});
        await newPage.close();
    })
});

describe('shared tags', () => {
    test('tag mainpage', async () => {
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Gemeinsame Tags'});
        await page.waitFor(1000);
        await page.screenshot({path: 'screenshots/13_administration/21_admin_gemeinsame_tags.png'});
        await expect(page).toClick('.x-grid3-cell-inner.x-grid3-col-name', {text: 'internal', clickCount: 2});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2000);
        await newPage.screenshot({path: 'screenshots/13_administration/22_admin_gemeinsame_tags_rechte.png'});
        await expect(newPage).toClick('span', {text: 'Kontexte'});
        await page.waitFor(500);
        await newPage.screenshot({path: 'screenshots/13_administration/23_admin_gemeinsame_tags_kontexte.png'});
        await newPage.close();
    });
});

describe('customfields', () => {
    test('mainpage', async () => {
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Zusatzfelder'});
        await page.waitFor(1000);
        await page.screenshot({path: 'screenshots/13_administration/27_admin_zusatzfelder.png'});
    });
    test('edit customfields', async () => {
        await expect(page).toClick('.t-app-admin button', {text: 'Zusatzfeld hinzufügen'});
        newPage = await lib.getNewWindow();
        await newPage.waitFor(2000);
        await newPage.screenshot({path: 'screenshots/13_administration/28_admin_zusatzfelder_neu.png'});
        newPage.close();
    });
});

describe('activSync', () => {
    test('mainpage', async () => {
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'ActiveSync Geräte'});
        await page.waitFor(1000);
        await page.screenshot({path: 'screenshots/13_administration/30_admin_activesync_devices.png'});
    });
    test('edit dialog', async () => {
        await expect(page).toClick('.t-app-admin .x-grid3-cell-inner.x-grid3-col-devicetype', {
            text: 'android',
            clickCount: 2
        });
        newPage = await lib.getNewWindow();
        await newPage.waitFor(3000);
        await newPage.screenshot({path: 'screenshots/13_administration/31_admin_activesync_devices_editieren.png'});
        await newPage.close()
    });
});


describe('access log', () => {
    test('mainpage', async () => {
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Zugriffslog'});
        await page.waitFor(1000);
        await page.screenshot({path: 'screenshots/13_administration/20_admin_zugriffslog.png'});
    })
});

describe('server info', () => {
    test('mainpage', async () => {
        await expect(page).toClick('.t-app-admin .tine-mainscreen-centerpanel-west span', {text: 'Server Informationen'});
        await page.waitFor(1000);
        await page.screenshot({path: 'screenshots/13_administration/29_admin_serverinfo.png'});
    })
});

afterAll(async () => {
    browser.close();
});
