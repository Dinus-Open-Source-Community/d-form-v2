import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, statSync, utimesSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

/** Isi file heartbeat yang ditulis setiap worker Playwright. */
export interface IHeartbeatPayload {
    pid: number;
    runId: string;
    startedAt: string;
}

/** Argumen pembentuk heartbeat worker. */
export interface IWorkerHeartbeatInput {
    project: string;
    workerIndex: number;
    runId: string;
}

/** Pegangan heartbeat; `stop()` menghapus file milik worker ini. */
export interface IHeartbeatHandle {
    filePath: string;
    stop: () => void;
}

const HEARTBEAT_REFRESH_MS = 5_000;
const HEARTBEAT_LIVE_MS = 30_000;

/** Folder akar heartbeat run, relatif terhadap root proyek. */
export function heartbeatRoot(): string {
    return resolve(process.cwd(), 'storage/app/e2e/heartbeats');
}

/** Folder heartbeat satu run. */
export function heartbeatDirectory(runId: string): string {
    return join(heartbeatRoot(), runId);
}

/** Path file heartbeat `<project>.<workerIndex>.json` untuk satu run. */
export function heartbeatFilePath(input: IWorkerHeartbeatInput): string {
    return join(heartbeatDirectory(input.runId), `${input.project}.${input.workerIndex}.json`);
}

/**
 * Mulai heartbeat worker: tulis file, segarkan mtime tiap 5 detik, hapus saat `stop()`.
 * Dipakai fixture worker-scoped agar crash meninggalkan file basi yang bisa disapu.
 */
export function startWorkerHeartbeat(input: IWorkerHeartbeatInput): IHeartbeatHandle {
    const filePath = heartbeatFilePath(input);
    const payload: IHeartbeatPayload = {
        pid: process.pid,
        runId: input.runId,
        startedAt: new Date().toISOString(),
    };

    mkdirSync(heartbeatDirectory(input.runId), { recursive: true });
    writeFileSync(filePath, JSON.stringify(payload), 'utf8');

    const timer = setInterval(() => {
        const now = new Date();

        try {
            utimesSync(filePath, now, now);
        } catch {
            // File bisa sudah dihapus oleh teardown; heartbeat dianggap berhenti.
        }
    }, HEARTBEAT_REFRESH_MS);

    timer.unref();

    return {
        filePath,
        stop: (): void => {
            clearInterval(timer);
            rmSync(filePath, { force: true });
        },
    };
}

/** Heartbeat hidup hanya bila mtime <= 30 detik, pid ada, dan cmdline-nya proses test runner. */
export function isHeartbeatLive(filePath: string, now: number = Date.now()): boolean {
    if (!existsSync(filePath)) {
        return false;
    }

    try {
        const modifiedAt = statSync(filePath).mtimeMs;

        if (now - modifiedAt > HEARTBEAT_LIVE_MS) {
            return false;
        }

        const payload = parseHeartbeat(readFileSync(filePath, 'utf8'));

        if (payload === null || !existsSync(`/proc/${payload.pid}`)) {
            return false;
        }

        const commandLine = readFileSync(`/proc/${payload.pid}/cmdline`, 'utf8').replaceAll('\0', ' ');

        return /playwright|node/i.test(commandLine);
    } catch {
        return false;
    }
}

/** Daftar file heartbeat satu run (kosong bila folder belum ada). */
export function listHeartbeats(runId: string): string[] {
    const directory = heartbeatDirectory(runId);

    if (!existsSync(directory)) {
        return [];
    }

    return readdirSync(directory)
        .filter((entry: string): boolean => entry.endsWith('.json'))
        .map((entry: string): string => join(directory, entry));
}

/** Daftar runId yang masih punya folder heartbeat (dipakai sweep untuk menghitung liveness). */
export function listHeartbeatRunIds(): string[] {
    const root = heartbeatRoot();

    if (!existsSync(root)) {
        return [];
    }

    return readdirSync(root, { withFileTypes: true })
        .filter((entry): boolean => entry.isDirectory())
        .map((entry: string): string => entry.name);
}

/** Apakah satu run masih punya minimal satu heartbeat hidup. */
export function hasLiveHeartbeat(runId: string, now: number = Date.now()): boolean {
    return listHeartbeats(runId).some((filePath: string): boolean => isHeartbeatLive(filePath, now));
}

/** Hapus folder heartbeat satu run setelah cleanup berhasil. */
export function removeRunHeartbeatDirectory(runId: string): void {
    rmSync(heartbeatDirectory(runId), { recursive: true, force: true });
}

/** Parse isi heartbeat dengan penyempitan eksplisit; `null` bila bentuknya tidak valid. */
function parseHeartbeat(raw: string): IHeartbeatPayload | null {
    let parsed: unknown;

    try {
        parsed = JSON.parse(raw);
    } catch {
        return null;
    }

    if (typeof parsed !== 'object' || parsed === null) {
        return null;
    }

    const candidate = parsed as { pid?: unknown; runId?: unknown; startedAt?: unknown };

    if (
        typeof candidate.pid !== 'number' ||
        !Number.isInteger(candidate.pid) ||
        candidate.pid <= 0 ||
        typeof candidate.runId !== 'string' ||
        typeof candidate.startedAt !== 'string'
    ) {
        return null;
    }

    return { pid: candidate.pid, runId: candidate.runId, startedAt: candidate.startedAt };
}
