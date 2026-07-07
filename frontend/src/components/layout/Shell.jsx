import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import Topbar from './Topbar';
import { X } from 'lucide-react';

export default function Shell() {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="flex h-screen overflow-hidden" style={{ background: 'var(--clr-body)' }}>
      {/* Desktop sidebar */}
      <div className="hidden lg:flex">
        <Sidebar />
      </div>

      {/* Mobile sidebar overlay */}
      {mobileOpen && (
        <>
          <div
            className="fixed inset-0 z-40 lg:hidden animate-fade-in"
            style={{ background: 'rgba(5,46,16,0.5)', backdropFilter: 'blur(4px)' }}
            onClick={() => setMobileOpen(false)}
          />
          <div className="fixed inset-y-0 left-0 z-50 flex lg:hidden animate-slide-in">
            <Sidebar onClose={() => setMobileOpen(false)} />
            <button
              onClick={() => setMobileOpen(false)}
              className="absolute top-4 right-4 p-1.5 rounded-lg"
              style={{ background: 'var(--clr-card)', color: 'var(--clr-muted)' }}
            >
              <X size={14} />
            </button>
          </div>
        </>
      )}

      {/* Main column */}
      <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
        <Topbar onMenuToggle={() => setMobileOpen((v) => !v)} />
        <main className="flex-1 overflow-y-auto p-4 sm:p-6" style={{ background: 'var(--clr-body)' }}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
