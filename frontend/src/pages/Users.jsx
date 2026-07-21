import { useCallback, useEffect, useMemo, useState } from 'react';
import api, { asRows } from '../api';
import { useAuth } from '../context/AuthContext';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import Modal from '../components/ui/Modal';
import PasswordInput from '../components/ui/PasswordInput';
import { CheckSquare, Edit, Plus, RefreshCw, Search, Shield, Trash2 } from 'lucide-react';

const roles = ['Administrator', 'Receptionist', 'Doctor', 'Inventory Officer', 'Pharmacy User', 'Lab User'];
const statuses = ['Active', 'Inactive'];

const moduleLabels = {
  dashboard: 'Dashboard',
  users: 'Users',
  doctors: 'Doctors',
  patients: 'Patients',
  appointments: 'Appointments',
  doctor_appointments: 'Doctor Appointments',
  payments: 'Payments',
  finance: 'Finance',
  audit_logs: 'Audit Logs',
  treatments: 'Treatments',
  followups: 'Follow-Ups',
  inventory: 'Inventory',
  pharmacy: 'Pharmacy',
  prescriptions: 'Prescriptions',
  laboratory: 'Laboratory',
  reports: 'Reports',
  settings: 'Settings',
};

const emptyForm = {
  full_name: '',
  username: '',
  role: 'Receptionist',
  password: '',
  status: 'Active',
  module_permissions: [],
};

function defaultRolePermissions(role, rolePermissions) {
  return rolePermissions?.[role] ?? [];
}

function inputStyle() {
  return { background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' };
}

export default function Users() {
  const { bootstrap, refresh } = useAuth();
  const rolePermissions = bootstrap?.role_permissions ?? {};
  const allModules = useMemo(() => Object.keys(moduleLabels), []);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [search, setSearch] = useState('');
  const [editor, setEditor] = useState(undefined);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/users');
      setUsers(asRows(data));
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const filtered = users.filter((user) => {
    const q = search.toLowerCase();
    return !q || [user.full_name, user.username, user.role, user.status]
      .some((value) => String(value ?? '').toLowerCase().includes(q));
  });

  const openCreate = () => {
    setEditor({
      ...emptyForm,
      module_permissions: defaultRolePermissions(emptyForm.role, rolePermissions),
    });
  };

  const openEdit = (user) => {
    setEditor({
      ...user,
      password: '',
      module_permissions: Array.isArray(user.module_permissions)
        ? user.module_permissions
        : defaultRolePermissions(user.role, rolePermissions),
    });
  };

  const remove = async (user) => {
    if (!window.confirm(`Delete ${user.full_name}?`)) return;
    setError('');
    try {
      await api.delete(`/users/${user.id}`);
      setMessage('User deleted.');
      await load();
      refresh();
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div className="space-y-5 animate-fade-in">
      <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Users</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
            Staff accounts, roles, and per-user access permissions.
          </p>
        </div>
        <button onClick={openCreate} className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold" style={{ background: '#7c3aed', color: '#ffffff' }}>
          <Plus size={15} />
          Add User
        </button>
      </div>

      {error && <Alert message={error} />}
      {message && <Alert message={message} variant="success" />}

      <div className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <div>
            <p className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>User Roles Management</p>
            <p className="text-xs mt-0.5" style={{ color: 'var(--clr-muted)' }}>{filtered.length} user(s)</p>
          </div>
          <div className="flex items-center gap-2">
            <div className="relative">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--clr-muted)' }} />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search users..."
                className="w-56 rounded-lg py-2 pl-9 pr-3 text-sm outline-none"
                style={inputStyle()}
              />
            </div>
            <button onClick={load} title="Refresh" className="p-2 rounded-lg" style={{ color: 'var(--clr-muted)', border: '1px solid var(--clr-border)' }}>
              <RefreshCw size={15} />
            </button>
          </div>
        </div>

        {loading ? <LoadingSpinner /> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr style={{ borderBottom: '1px solid var(--clr-border)' }}>
                  {['Name', 'Username', 'Role', 'Status', 'Access', 'Actions'].map((heading) => (
                    <th key={heading} className="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-widest whitespace-nowrap" style={{ color: 'var(--clr-section)' }}>
                      {heading}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {filtered.map((user) => {
                  const permissions = Array.isArray(user.module_permissions)
                    ? user.module_permissions
                    : defaultRolePermissions(user.role, rolePermissions);
                  return (
                    <tr key={user.id} style={{ borderBottom: '1px solid var(--clr-border)' }}>
                      <td className="px-5 py-3 font-semibold" style={{ color: 'var(--clr-text)' }}>{user.full_name}</td>
                      <td className="px-5 py-3 font-mono text-xs" style={{ color: 'var(--clr-muted)' }}>{user.username}</td>
                      <td className="px-5 py-3" style={{ color: 'var(--clr-text)' }}>{user.role}</td>
                      <td className="px-5 py-3">
                        <span className="px-2 py-1 rounded-full text-[11px] font-bold" style={{ background: user.status === 'Active' ? '#7c3aed18' : '#f8717118', color: user.status === 'Active' ? '#7c3aed' : '#f87171' }}>
                          {user.status}
                        </span>
                      </td>
                      <td className="px-5 py-3">
                        <div className="flex flex-wrap gap-1 max-w-md">
                          {permissions.slice(0, 5).map((module) => (
                            <span key={module} className="px-2 py-1 rounded-full text-[10px] font-semibold" style={{ background: 'var(--clr-search-bg)', color: 'var(--clr-muted)' }}>
                              {moduleLabels[module] ?? module}
                            </span>
                          ))}
                          {permissions.length > 5 && (
                            <span className="px-2 py-1 rounded-full text-[10px] font-semibold" style={{ background: 'var(--clr-search-bg)', color: 'var(--clr-muted)' }}>
                              +{permissions.length - 5}
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-5 py-3">
                        <div className="flex justify-end gap-1">
                          <button onClick={() => openEdit(user)} title="Edit access" className="p-2 rounded-lg" style={{ color: 'var(--clr-muted)' }}>
                            <Edit size={15} />
                          </button>
                          <button onClick={() => remove(user)} title="Delete user" className="p-2 rounded-lg" style={{ color: '#f87171' }}>
                            <Trash2 size={15} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {editor !== undefined && (
        <UserEditor
          user={editor}
          allModules={allModules}
          rolePermissions={rolePermissions}
          saving={saving}
          setSaving={setSaving}
          onClose={() => setEditor(undefined)}
          onSaved={async () => {
            setEditor(undefined);
            setMessage('User saved.');
            await load();
            refresh();
          }}
        />
      )}
    </div>
  );
}

function UserEditor({ user, allModules, rolePermissions, saving, setSaving, onClose, onSaved }) {
  const editing = Boolean(user.id);
  const [form, setForm] = useState(user);
  const [error, setError] = useState('');

  const set = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

  const changeRole = (role) => {
    setForm((prev) => ({
      ...prev,
      role,
      module_permissions: defaultRolePermissions(role, rolePermissions),
    }));
  };

  const toggleModule = (module) => {
    setForm((prev) => {
      const current = prev.module_permissions ?? [];
      return {
        ...prev,
        module_permissions: current.includes(module)
          ? current.filter((item) => item !== module)
          : [...current, module],
      };
    });
  };

  const selectAll = () => set('module_permissions', allModules);
  const clearAll = () => set('module_permissions', []);
  const useRoleDefaults = () => set('module_permissions', defaultRolePermissions(form.role, rolePermissions));

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        full_name: form.full_name,
        username: form.username,
        role: form.role,
        status: form.status,
        module_permissions: form.module_permissions ?? [],
      };
      if (form.password) payload.password = form.password;
      if (editing) {
        await api.put(`/users/${form.id}`, payload);
      } else {
        await api.post('/users', payload);
      }
      onSaved();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title={editing ? 'Edit User Access' : 'Add User'}
      subtitle="Choose the role and tick the modules this user can see."
      onClose={onClose}
    >
      {error && <div className="mb-4"><Alert message={error} /></div>}

      <form onSubmit={submit} className="space-y-5">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label className="space-y-1">
            <span className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>Full Name</span>
            <input required value={form.full_name ?? ''} onChange={(e) => set('full_name', e.target.value)} className="w-full rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
          </label>
          <label className="space-y-1">
            <span className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>Username</span>
            <input required value={form.username ?? ''} onChange={(e) => set('username', e.target.value)} className="w-full rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
          </label>
          <label className="space-y-1">
            <span className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>Role</span>
            <select value={form.role} onChange={(e) => changeRole(e.target.value)} className="w-full rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              {roles.map((role) => <option key={role}>{role}</option>)}
            </select>
          </label>
          <label className="space-y-1">
            <span className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>Status</span>
            <select value={form.status} onChange={(e) => set('status', e.target.value)} className="w-full rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              {statuses.map((status) => <option key={status}>{status}</option>)}
            </select>
          </label>
          <label className="md:col-span-2 space-y-1">
            <span className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>{editing ? 'New Password (optional)' : 'Password'}</span>
            <PasswordInput
              required={!editing}
              value={form.password ?? ''}
              onChange={(e) => set('password', e.target.value)}
              placeholder="Strong password: upper, lower, number, symbol"
              className="w-full rounded-lg px-3 py-2 text-sm outline-none"
              style={inputStyle()}
            />
          </label>
        </div>

        <div className="rounded-xl p-4 space-y-4" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div className="flex items-center gap-2">
              <Shield size={16} className="text-violet-600" />
              <div>
                <p className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>Module Access</p>
                <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>{(form.module_permissions ?? []).length} selected</p>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <button type="button" onClick={useRoleDefaults} className="px-3 py-1.5 rounded-lg text-xs font-bold" style={{ border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}>Role Default</button>
              <button type="button" onClick={selectAll} className="px-3 py-1.5 rounded-lg text-xs font-bold" style={{ border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}>Select All</button>
              <button type="button" onClick={clearAll} className="px-3 py-1.5 rounded-lg text-xs font-bold" style={{ border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}>Clear</button>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            {allModules.map((module) => {
              const checked = (form.module_permissions ?? []).includes(module);
              return (
                <label key={module} className="flex items-center gap-3 rounded-lg px-3 py-2 cursor-pointer" style={{ background: checked ? '#7c3aed18' : 'var(--clr-card)', border: `1px solid ${checked ? '#7c3aed55' : 'var(--clr-border)'}` }}>
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={() => toggleModule(module)}
                    className="h-4 w-4 accent-violet-600"
                  />
                  <CheckSquare size={14} style={{ color: checked ? '#7c3aed' : 'var(--clr-muted)' }} />
                  <span className="text-sm font-semibold" style={{ color: checked ? '#7c3aed' : 'var(--clr-text)' }}>
                    {moduleLabels[module] ?? module}
                  </span>
                </label>
              );
            })}
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-4" style={{ borderTop: '1px solid var(--clr-border)' }}>
          <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg text-sm font-bold" style={{ background: 'var(--clr-hover)', color: 'var(--clr-muted)', border: '1px solid var(--clr-border)' }}>
            Cancel
          </button>
          <button disabled={saving} className="px-5 py-2 rounded-lg text-sm font-bold" style={{ background: '#7c3aed', color: '#ffffff' }}>
            {saving ? 'Saving...' : 'Save User'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
