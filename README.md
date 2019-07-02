# SaaS (Software as a Service)

Processwire module to restrict pages by a shared saas_id.

### Functionality

A (hidden) saas_id field is created, and added to the User-template.
Add saas_id field to templates, by configuring this module.
The saas_id of the user is automaticaly added to all pages the user creates.
If the saas_id of the user, and the saas_id of the page match, access is granted.

For all listings, add the selector ```saas_id=user()->saas_id``` or equevalent!!

#### Support forum:


## License

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

(See included LICENSE file for full license text.)