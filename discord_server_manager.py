import tkinter as tk
from tkinter import ttk, messagebox, scrolledtext
import requests
import threading
import time

DISCORD_API = "https://discord.com/api/v10"

def get_guilds(token):
    headers = {"Authorization": token, "Content-Type": "application/json"}
    r = requests.get(f"{DISCORD_API}/users/@me/guilds", headers=headers)
    if r.status_code == 200:
        return r.json()
    return None

def leave_guild(token, guild_id):
    headers = {"Authorization": token, "Content-Type": "application/json"}
    r = requests.delete(f"{DISCORD_API}/users/@me/guilds/{guild_id}", headers=headers)
    return r.status_code in (200, 204)

def delete_guild(token, guild_id):
    headers = {"Authorization": token, "Content-Type": "application/json"}
    r = requests.delete(f"{DISCORD_API}/guilds/{guild_id}", headers=headers)
    return r.status_code in (200, 204)


class DiscordManagerApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Discord Server Manager")
        self.root.geometry("650x600")
        self.root.resizable(False, False)
        self.root.configure(bg="#2b2d31")

        self.token = tk.StringVar()
        self.guilds = []
        self.checkboxes = {}
        self.check_vars = {}

        self._build_ui()

    def _build_ui(self):
        # Header
        tk.Label(self.root, text="Discord Server Manager", font=("Segoe UI", 16, "bold"),
                 bg="#2b2d31", fg="#ffffff").pack(pady=(16, 4))
        tk.Label(self.root, text="Attention : utilise uniquement sur ton propre compte.",
                 font=("Segoe UI", 9), bg="#2b2d31", fg="#f0b232").pack()

        # Token input
        frame_token = tk.Frame(self.root, bg="#2b2d31")
        frame_token.pack(fill="x", padx=20, pady=(12, 4))

        tk.Label(frame_token, text="Token Discord :", font=("Segoe UI", 10),
                 bg="#2b2d31", fg="#b5bac1").pack(anchor="w")

        token_row = tk.Frame(frame_token, bg="#2b2d31")
        token_row.pack(fill="x", pady=4)

        self.token_entry = tk.Entry(token_row, textvariable=self.token, show="*",
                                    font=("Segoe UI", 10), bg="#1e1f22", fg="#ffffff",
                                    insertbackground="white", relief="flat", bd=6)
        self.token_entry.pack(side="left", fill="x", expand=True)

        self.show_btn = tk.Button(token_row, text="Voir", command=self._toggle_token,
                                  bg="#404249", fg="#ffffff", relief="flat", padx=8,
                                  font=("Segoe UI", 9), cursor="hand2")
        self.show_btn.pack(side="left", padx=(6, 0))

        self.load_btn = tk.Button(frame_token, text="Charger mes serveurs",
                                  command=self._load_guilds_thread,
                                  bg="#5865f2", fg="#ffffff", relief="flat",
                                  font=("Segoe UI", 10, "bold"), padx=12, pady=6,
                                  cursor="hand2")
        self.load_btn.pack(anchor="w", pady=(8, 0))

        # Toolbar checkboxes
        toolbar = tk.Frame(self.root, bg="#2b2d31")
        toolbar.pack(fill="x", padx=20, pady=(10, 0))

        tk.Button(toolbar, text="Tout cocher", command=self._select_all,
                  bg="#404249", fg="#ffffff", relief="flat", font=("Segoe UI", 9),
                  padx=8, pady=4, cursor="hand2").pack(side="left", padx=(0, 6))
        tk.Button(toolbar, text="Tout décocher", command=self._deselect_all,
                  bg="#404249", fg="#ffffff", relief="flat", font=("Segoe UI", 9),
                  padx=8, pady=4, cursor="hand2").pack(side="left")

        self.count_label = tk.Label(toolbar, text="", font=("Segoe UI", 9),
                                    bg="#2b2d31", fg="#b5bac1")
        self.count_label.pack(side="right")

        # Scrollable server list
        list_frame = tk.Frame(self.root, bg="#1e1f22", relief="flat")
        list_frame.pack(fill="both", expand=True, padx=20, pady=(6, 0))

        canvas = tk.Canvas(list_frame, bg="#1e1f22", highlightthickness=0)
        scrollbar = ttk.Scrollbar(list_frame, orient="vertical", command=canvas.yview)
        self.scroll_frame = tk.Frame(canvas, bg="#1e1f22")

        self.scroll_frame.bind("<Configure>",
            lambda e: canvas.configure(scrollregion=canvas.bbox("all")))

        canvas.create_window((0, 0), window=self.scroll_frame, anchor="nw")
        canvas.configure(yscrollcommand=scrollbar.set)
        canvas.pack(side="left", fill="both", expand=True)
        scrollbar.pack(side="right", fill="y")
        canvas.bind_all("<MouseWheel>",
            lambda e: canvas.yview_scroll(int(-1*(e.delta/120)), "units"))

        # Log zone
        self.log = scrolledtext.ScrolledText(self.root, height=5, bg="#111214",
                                             fg="#b5bac1", font=("Consolas", 9),
                                             relief="flat", state="disabled")
        self.log.pack(fill="x", padx=20, pady=(6, 0))

        # Action button
        self.action_btn = tk.Button(self.root, text="Quitter / Supprimer les serveurs sélectionnés",
                                    command=self._confirm_action,
                                    bg="#ed4245", fg="#ffffff", relief="flat",
                                    font=("Segoe UI", 10, "bold"), pady=8,
                                    cursor="hand2", state="disabled")
        self.action_btn.pack(fill="x", padx=20, pady=10)

    def _toggle_token(self):
        if self.token_entry.cget("show") == "*":
            self.token_entry.config(show="")
            self.show_btn.config(text="Cacher")
        else:
            self.token_entry.config(show="*")
            self.show_btn.config(text="Voir")

    def _log(self, msg):
        self.log.config(state="normal")
        self.log.insert("end", msg + "\n")
        self.log.see("end")
        self.log.config(state="disabled")

    def _load_guilds_thread(self):
        self.load_btn.config(state="disabled", text="Chargement...")
        threading.Thread(target=self._load_guilds, daemon=True).start()

    def _load_guilds(self):
        token = self.token.get().strip()
        if not token:
            messagebox.showerror("Erreur", "Entre ton token Discord.")
            self.load_btn.config(state="normal", text="Charger mes serveurs")
            return

        self._log("Connexion à Discord...")
        guilds = get_guilds(token)

        if guilds is None:
            self._log("Token invalide ou erreur API.")
            messagebox.showerror("Erreur", "Token invalide ou erreur de connexion.")
            self.load_btn.config(state="normal", text="Charger mes serveurs")
            return

        self.guilds = guilds
        self.root.after(0, self._populate_list)

    def _populate_list(self):
        for widget in self.scroll_frame.winfo_children():
            widget.destroy()
        self.check_vars.clear()

        for guild in self.guilds:
            var = tk.BooleanVar()
            self.check_vars[guild["id"]] = var
            is_owner = guild.get("owner", False)

            label = f"{'[OWNER] ' if is_owner else ''}{guild['name']}"
            color = "#ff7b72" if is_owner else "#e3e5e8"

            cb = tk.Checkbutton(self.scroll_frame, text=label, variable=var,
                                 bg="#1e1f22", fg=color, selectcolor="#1e1f22",
                                 activebackground="#1e1f22", activeforeground=color,
                                 font=("Segoe UI", 10), anchor="w",
                                 command=self._update_count)
            cb.pack(fill="x", padx=10, pady=2)

        self._log(f"{len(self.guilds)} serveurs chargés.")
        self._update_count()
        self.action_btn.config(state="normal")
        self.load_btn.config(state="normal", text="Recharger")

    def _select_all(self):
        for var in self.check_vars.values():
            var.set(True)
        self._update_count()

    def _deselect_all(self):
        for var in self.check_vars.values():
            var.set(False)
        self._update_count()

    def _update_count(self):
        n = sum(1 for v in self.check_vars.values() if v.get())
        self.count_label.config(text=f"{n} sélectionné(s)")

    def _confirm_action(self):
        selected = [g for g in self.guilds if self.check_vars.get(g["id"], tk.BooleanVar()).get()]
        if not selected:
            messagebox.showinfo("Info", "Aucun serveur sélectionné.")
            return

        owners = [g for g in selected if g.get("owner")]
        members = [g for g in selected if not g.get("owner")]

        msg = f"Tu vas :\n"
        if members:
            msg += f"• Quitter {len(members)} serveur(s)\n"
        if owners:
            msg += f"• SUPPRIMER {len(owners)} serveur(s) dont tu es owner\n"
        msg += "\nCette action est irréversible. Continuer ?"

        if messagebox.askyesno("Confirmation", msg):
            threading.Thread(target=self._process_selected, args=(selected,), daemon=True).start()

    def _process_selected(self, selected):
        self.action_btn.config(state="disabled")
        token = self.token.get().strip()
        success, fail = 0, 0

        for guild in selected:
            gid = guild["id"]
            name = guild["name"]
            is_owner = guild.get("owner", False)

            if is_owner:
                ok = delete_guild(token, gid)
                action = "Supprimé"
            else:
                ok = leave_guild(token, gid)
                action = "Quitté"

            if ok:
                self._log(f"✓ {action} : {name}")
                success += 1
            else:
                self._log(f"✗ Échec : {name}")
                fail += 1

            time.sleep(0.8)  # évite le rate limit Discord

        self._log(f"\nTerminé — {success} OK, {fail} échec(s).")
        self.root.after(0, lambda: self._load_guilds_thread())


if __name__ == "__main__":
    root = tk.Tk()
    app = DiscordManagerApp(root)
    root.mainloop()
