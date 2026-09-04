import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const backendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

export type QaAccount = {
  id?: number;
  name: string;
  email: string;
  role: string;
  students?: string[];
  student_ids?: number[];
};

export type QaManifest = {
  password: string;
  director: QaAccount;
  teachers: QaAccount[];
  parents: QaAccount[];
  other: {
    director: QaAccount;
    teacher: QaAccount;
    parent: QaAccount;
    student: { id: number; name: string };
  };
};

const fallback: QaManifest = {
  password: 'QaSchool!2026',
  director: { name: 'Director QA', email: 'director.qa@qa.aulasync.test', role: 'director' },
  teachers: Array.from({ length: 5 }, (_, i) => ({
    name: `Docente QA ${String(i + 1).padStart(2, '0')}`,
    email: `docente.qa.${String(i + 1).padStart(2, '0')}@qa.aulasync.test`,
    role: 'profesor',
  })),
  parents: Array.from({ length: 20 }, (_, i) => ({
    name: `Representante QA ${String(i + 1).padStart(2, '0')}`,
    email: `representante.qa.${String(i + 1).padStart(2, '0')}@qa.aulasync.test`,
    role: 'representante',
  })),
  other: {
    director: { name: 'Director QA Other', email: 'director.qa.other@qa.aulasync.test', role: 'director' },
    teacher: { name: 'Docente QA Other', email: 'docente.qa.other@qa.aulasync.test', role: 'profesor' },
    parent: { name: 'Representante QA Other', email: 'representante.qa.other@qa.aulasync.test', role: 'representante' },
    student: { id: 0, name: 'Alumno QA Other' },
  },
};

export function loadManifest(): QaManifest {
  const file = path.join(backendRoot, 'storage', 'app', 'qa', 'accounts.json');
  if (!fs.existsSync(file)) {
    return fallback;
  }
  try {
    return { ...fallback, ...JSON.parse(fs.readFileSync(file, 'utf8')) };
  } catch {
    return fallback;
  }
}
