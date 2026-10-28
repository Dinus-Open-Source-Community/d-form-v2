import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { basename, join } from 'node:path';
import { expect, test } from '@playwright/test';
import playwrightConfig from '../../playwright.config';

const PLACEHOLDER = 'tests/e2e/harness-projects.mobile.spec.ts';
const BROWSER_PROJECTS = ['chromium-desktop', 'chromium-mobile'] as const;

/** Satu test terdaftar beserta project yang menjalankannya. */
interface IListedTest {
    projectName?: string;
}

/** Satu spec pada laporan JSON `playwright test --list`. */
interface IListedSpec {
    file?: string;
    tests?: IListedTest[];
}

/** Suite laporan JSON; berkas spec menjadi suite akar, sub-folder menjadi suite bersarang. */
interface IListedSuite {
    file?: string;
    specs?: IListedSpec[];
    suites?: IListedSuite[];
}

/** Bentuk minimum laporan JSON yang dipakai assertion project. */
interface IListReport {
    suites?: IListedSuite[];
}

/**
 * Path CLI Playwright yang benar-benar terpasang, dicari lewat resolusi modul Node
 * supaya worktree dengan `node_modules` tidak wajib punya salinan sendiri.
 */
function playwrightCliPath(): string {
    const resolveFromProject = createRequire(join(process.cwd(), 'package.json'));

    return resolveFromProject.resolve('@playwright/test/cli');
}

/** Jalankan CLI Playwright di proses terpisah dan kembalikan stdout/stderr/exit code. */
function runPlaywright(args: string[]): { status: number; stdout: string; stderr: string } {
    try {
        const stdout = execFileSync(process.execPath, [playwrightCliPath(), 'test', ...args], {
            encoding: 'utf8',
            env: process.env,
            maxBuffer: 8 * 1024 * 1024,
        });

        return { status: 0, stdout, stderr: '' };
    } catch (error) {
        const failure = error as { status?: number; stdout?: string; stderr?: string; message?: string };

        return {
            status: failure.status ?? 1,
            stdout: failure.stdout ?? '',
            stderr: failure.stderr ?? failure.message ?? '',
        };
    }
}

/** Ratakan suite JSON reporter menjadi daftar spec; nama berkas ikut agar bisa difilter. */
function flattenSpecs(suites: IListedSuite[] | undefined): IListedSpec[] {
    const specs: IListedSpec[] = [];

    for (const suite of suites ?? []) {
        for (const spec of suite.specs ?? []) {
            specs.push({ file: spec.file ?? suite.file, tests: spec.tests ?? [] });
        }

        specs.push(...flattenSpecs(suite.suites));
    }

    return specs;
}

let cachedListReport: IListedSpec[] | null = null;

/** Laporan `--list` seluruh project; di-cache karena satu invokasi CLI butuh process spawn. */
function listReport(): IListedSpec[] {
    if (cachedListReport !== null) {
        return cachedListReport;
    }

    const result = runPlaywright(['--list', '--reporter=json']);

    if (result.status !== 0) {
        throw new Error(`playwright --list exited ${String(result.status)}: ${result.stderr}`);
    }

    cachedListReport = flattenSpecs((JSON.parse(result.stdout) as IListReport).suites);

    return cachedListReport;
}

/** Nama project yang benar-benar menjalankan satu berkas spec. */
function projectsForSpecFile(file: string): string[] {
    const target = basename(file);
    const projects = new Set<string>();

    for (const spec of listReport()) {
        if (spec.file !== target) {
            continue;
        }

        for (const listed of spec.tests ?? []) {
            if (listed.projectName !== undefined) {
                projects.add(listed.projectName);
            }
        }
    }

    return [...projects].sort();
}

/** Berkas spec yang dijalankan satu project. */
function specFilesForProject(project: string): string[] {
    const files = new Set<string>();

    for (const spec of listReport()) {
        const runs = (spec.tests ?? []).some((listed: IListedTest): boolean => listed.projectName === project);

        if (runs && spec.file !== undefined) {
            files.add(spec.file);
        }
    }

    return [...files].sort();
}

/** H-projects: graph project, script project, dan test matching harus sesuai kontrak. */
test('H-projects: project graph, matching desktop/mobile, dan placeholder mobile', async (): Promise<void> => {
    const mobileList = runPlaywright(['--project=chromium-mobile', '--list']);
    expect(mobileList.status).toBe(0);
    expect(`${mobileList.stdout}${mobileList.stderr}`).toContain('H-projects mobile placeholder');

    const projects = playwrightConfig.projects ?? [];

    for (const name of BROWSER_PROJECTS) {
        const project = projects.find((entry): boolean => entry.name === name);

        expect(project, `project ${name} must exist`).toBeTruthy();
        expect(project?.dependencies).toEqual(['setup']);
    }

    expect(projectsForSpecFile(PLACEHOLDER)).toEqual(['chromium-mobile']);
    expect(projectsForSpecFile('tests/e2e/harness-projects.spec.ts')).toEqual([
        'chromium-desktop',
        'chromium-mobile',
    ]);
    expect(specFilesForProject('chromium-mobile').filter((file: string): boolean => file.endsWith('.desktop.spec.ts'))).toEqual(
        [],
    );
});
